<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Domain\Events\DeviceRegistered;
use Modules\Identity\Domain\Models\UserDevice;
use Shared\Support\BusinessRuleViolation;

/**
 * تسجيل جهاز للمستخدم الحالي (للإشعارات الفورية).
 *
 * نفس رمز الإشعارات لا يتكرر على جهازَين نشطين. تسجيل متكرر لنفس المستخدم
 * بنفس رمز الإشعارات (مثل تسجيل الدخول من نفس الهاتف مرارًا) يحدّث الجهاز
 * الموجود بدل إنشاء صفّ مكرر — يمنع تراكم صفوف user_devices واحتمال إشعار
 * مزدوج لاحقًا حين يُستهلك canReceivePush().
 */
final readonly class RegisterDevice
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function execute(string $userId, array $attributes): UserDevice
    {
        $pushToken = $attributes['push_token'] ?? null;
        $pushToken = is_string($pushToken) && $pushToken !== '' ? $pushToken : null;

        if ($pushToken !== null) {
            $clash = UserDevice::query()
                ->active()
                ->where('push_token', $pushToken)
                ->where('user_id', '!=', $userId)
                ->exists();

            if ($clash) {
                throw BusinessRuleViolation::make(
                    'identity.push_token_in_use',
                    'identity::errors.push_token_in_use',
                );
            }
        }

        /** @var UserDevice $device */
        $device = DB::transaction(function () use ($userId, $attributes, $pushToken): UserDevice {
            $existing = $pushToken !== null
                ? UserDevice::query()->forUser($userId)->active()->where('push_token', $pushToken)->first()
                : null;

            if ($existing !== null) {
                $existing->update([
                    'device_name' => $attributes['device_name'] ?? $existing->device_name,
                    'platform' => $attributes['platform'] ?? $existing->platform,
                    'last_used_at' => now(),
                ]);

                return $existing;
            }

            return UserDevice::query()->create([
                'user_id' => $userId,
                'device_name' => $attributes['device_name'] ?? null,
                'platform' => $attributes['platform'] ?? null,
                'push_token' => $pushToken,
            ]);
        });

        if ($device->wasRecentlyCreated) {
            Event::dispatch(new DeviceRegistered(
                deviceId: $device->id,
                userId: $userId,
                platform: $device->platform,
            ));
        }

        return $device;
    }
}

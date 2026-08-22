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
 * نفس رمز الإشعارات لا يتكرر على جهازَين نشطين.
 */
final readonly class RegisterDevice
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(string $userId, array $attributes): UserDevice
    {
        $pushToken = $attributes['push_token'] ?? null;

        if (is_string($pushToken) && $pushToken !== '') {
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
            return UserDevice::query()->create([
                'user_id' => $userId,
                'device_name' => $attributes['device_name'] ?? null,
                'platform' => $attributes['platform'] ?? null,
                'push_token' => $pushToken,
            ]);
        });

        Event::dispatch(new DeviceRegistered(
            deviceId: $device->id,
            userId: $userId,
            platform: $device->platform,
        ));

        return $device;
    }
}

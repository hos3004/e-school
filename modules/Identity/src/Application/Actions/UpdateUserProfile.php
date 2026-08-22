<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\Domain\Events\UserProfileUpdated;
use Modules\Identity\Domain\Models\User;

/**
 * تحديث الملف الشخصي — الاسم، الهاتف، اللغة، المنطقة الزمنية.
 *
 * البريد لا يُعدَّل هنا (يتطلب تدفق تحقق مستقل). كلمة المرور عبر
 * UpdatePassword. الحقول المتغيرة فقط تُحدَّث ويُسجَّل ما تغيّر.
 */
final readonly class UpdateUserProfile
{
    /**
     * @param array<string, mixed> $attributes حقول مسموحة فقط
     */
    public function execute(User $user, array $attributes): User
    {
        $allowed = ['name', 'phone', 'phone_country', 'locale', 'timezone', 'avatar_path'];

        $changes = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $attributes)) {
                continue;
            }

            $newValue = $attributes[$field];
            if ($newValue === $user->{$field}) {
                continue;
            }

            $changes[$field] = [
                'old' => $user->{$field},
                'new' => $newValue,
            ];
        }

        /** @var User $user */
        $user = DB::transaction(function () use ($user, $changes): User {
            if ($changes !== []) {
                $user->fill(array_map(
                    static fn (array $change): mixed => $change['new'],
                    $changes,
                ));
                $user->save();
            }

            return $user;
        });

        if ($changes !== []) {
            Event::dispatch(new UserProfileUpdated(
                userId: $user->id,
                organizationId: $user->organization_id,
                changed: $changes,
            ));
        }

        return $user;
    }
}

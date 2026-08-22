<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;

/**
 * تغيير كلمة المرور من داخل الحساب — يتطلب كلمة المرور الحالية.
 */
final readonly class UpdatePassword
{
    public function execute(User $user, string $currentPassword, string $newPassword): User
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw BusinessRuleViolation::make(
                'identity.current_password_wrong',
                'identity::errors.current_password_wrong',
            );
        }

        if (Hash::check($newPassword, $user->password)) {
            throw BusinessRuleViolation::make(
                'identity.password_unchanged',
                'identity::errors.password_unchanged',
            );
        }

        /** @var User $user */
        $user = DB::transaction(function () use ($user, $newPassword): User {
            $user->password = Hash::make($newPassword);
            $user->save();

            return $user;
        });

        return $user;
    }
}

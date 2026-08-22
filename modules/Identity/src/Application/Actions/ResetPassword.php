<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Events\PasswordResetCompleted;
use Modules\Identity\Domain\Models\PasswordResetToken;
use Modules\Identity\Domain\Models\User;
use Shared\Support\BusinessRuleViolation;

/**
 * إتمام إعادة تعيين كلمة المرور برمز صالح وغير منتهٍ.
 *
 * عند النجاح: يُحذف الرمز، ويُبث حدث الاكتمال ليُبطل باقي الجلسات.
 * مدة الصلاحية تأتي من config('auth.passwords.users.expire') — لا رقم هنا.
 */
final readonly class ResetPassword
{
    public function execute(string $email, string $token, string $newPassword): User
    {
        /** @var PasswordResetToken|null $record */
        $record = PasswordResetToken::query()->find($email);

        if ($record === null || !Hash::check($token, $record->token)) {
            throw BusinessRuleViolation::make(
                'identity.reset_token_invalid',
                'identity::errors.reset_token_invalid',
            );
        }

        if (!$record->isFresh()) {
            throw BusinessRuleViolation::make(
                'identity.reset_token_expired',
                'identity::errors.reset_token_expired',
            );
        }

        /** @var User $user */
        $user = DB::transaction(function () use ($email, $newPassword, $record): User {
            /** @var User|null $user */
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                throw BusinessRuleViolation::make(
                    'identity.reset_token_invalid',
                    'identity::errors.reset_token_invalid',
                );
            }

            $user->password = Hash::make($newPassword);
            $user->save();

            $record->delete();

            return $user;
        });

        Event::dispatch(new PasswordResetCompleted(
            userId: $user->id,
            organizationId: $user->organization_id,
        ));

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Modules\Identity\Domain\Events\PasswordResetCompleted;
use Modules\Identity\Domain\Models\User;

/**
 * تنفيذ إعادة تعيين كلمة المرور عبر تدفق Fortify.
 *
 * يبقى في جذر التركيب لأنه يربط عقد Fortify بنموذج Identity، ويطلق
 * حدث النطاق PasswordResetCompleted ليعيد AccessControl إبطال الجلسات
 * والأجهزة القديمة وفق docs/15-security-model.md.
 */
final class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param array<string, mixed> $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'token' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ])->validate();

        DB::transaction(function () use ($user, $input): void {
            $user->forceFill([
                'password' => Hash::make((string) $input['password']),
                'remember_token' => Str::random(60),
            ])->save();

            Event::dispatch(new PasswordResetCompleted(
                userId: (string) $user->getKey(),
                organizationId: (string) $user->organization_id,
                actorId: (string) $user->getKey(),
            ));
        });
    }
}

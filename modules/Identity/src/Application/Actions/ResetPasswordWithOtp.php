<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Events\PasswordResetCompleted;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Shared\Support\BusinessRuleViolation;

/**
 * إكمال إعادة تعيين كلمة المرور برمز OTP للحسابات ذات الهاتف فقط.
 *
 * يتحقق من صلاحية الرمز، حد المحاولات، والانتهاء (15 دقيقة)،
 * ثم يحدّث كلمة المرور ويبث حدث الإكمال لتطهير الجلسات.
 */
final readonly class ResetPasswordWithOtp
{
    public function execute(string $organizationId, string $phone, string $otp, string $newPassword): User
    {
        $normalizedPhone = IssuePhonePasswordResetOtp::normalizePhone($phone);

        if ($normalizedPhone === null || trim($otp) === '') {
            throw $this->invalidOtp();
        }

        /** @var array{status: 'invalid'}|array{status: 'success', user: User} $outcome */
        $outcome = DB::transaction(function () use ($organizationId, $normalizedPhone, $otp, $newPassword): array {
            $matches = User::query()
                ->forOrganization($organizationId)
                ->active()
                ->where('phone', $normalizedPhone)
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($matches->count() !== 1) {
                return ['status' => 'invalid'];
            }

            $user = $matches->first();

            if (!$user instanceof User) {
                return ['status' => 'invalid'];
            }

            $record = DB::table('phone_password_reset_tokens')
                ->where('user_id', $user->id)
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if ($record === null || CarbonImmutable::parse($record->expires_at)->isPast()) {
                DB::table('phone_password_reset_tokens')->where('user_id', $user->id)->delete();

                return ['status' => 'invalid'];
            }

            $maxAttempts = (int) config('identity.phone_password_reset.max_verification_attempts');
            if ((int) $record->attempts >= $maxAttempts || !Hash::check($otp, (string) $record->token_hash)) {
                $attempts = (int) $record->attempts + 1;

                if ($attempts >= $maxAttempts) {
                    DB::table('phone_password_reset_tokens')->where('user_id', $user->id)->delete();
                } else {
                    DB::table('phone_password_reset_tokens')
                        ->where('user_id', $user->id)
                        ->update(['attempts' => $attempts, 'updated_at' => now()->utc()]);
                }

                return ['status' => 'invalid'];
            }

            $user->forceFill([
                'password' => Hash::make($newPassword),
                'remember_token' => Str::random(60),
            ])->save();

            UserDevice::query()->forUser($user->id)->active()->update([
                'revoked_at' => now()->utc(),
                'push_token' => null,
            ]);

            DB::table('phone_password_reset_tokens')->where('user_id', $user->id)->delete();

            return ['status' => 'success', 'user' => $user];
        });

        if ($outcome['status'] !== 'success') {
            throw $this->invalidOtp();
        }

        $user = $outcome['user'];

        Event::dispatch(new PasswordResetCompleted(
            userId: $user->id,
            organizationId: $user->organization_id,
        ));

        return $user;
    }

    private function invalidOtp(): BusinessRuleViolation
    {
        return BusinessRuleViolation::make(
            'identity.phone_reset_invalid',
            'identity::errors.phone_reset_invalid',
        );
    }
}

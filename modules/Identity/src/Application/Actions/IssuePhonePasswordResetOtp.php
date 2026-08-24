<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use Modules\Identity\Domain\Events\PhonePasswordResetRequested;
use Modules\Identity\Domain\Models\User;
use Throwable;

/**
 * طلب إعادة تعيين كلمة المرور برمز OTP للحسابات ذات الهاتف فقط (phone-only).
 *
 * الاستجابة متساوية دائمًا سواء وُجد الهاتف أم لا (منع حصر الحسابات)،
 * والرمز الخام لا يُرجع للمتصل أبدًا — يبث عبر حدث القناة.
 */
final readonly class IssuePhonePasswordResetOtp
{
    public function __construct(
        private PhonePasswordResetOtpDelivery $delivery,
    ) {}

    public function execute(string $organizationId, string $phone): void
    {
        $normalizedPhone = self::normalizePhone($phone);

        if ($normalizedPhone === null) {
            return;
        }

        /** @var array{user_id: string, organization_id: string, phone: string, otp: string, expires_at: CarbonImmutable}|null $issued */
        $issued = DB::transaction(function () use ($organizationId, $normalizedPhone): ?array {
            $matches = User::query()
                ->forOrganization($organizationId)
                ->active()
                ->where('phone', $normalizedPhone)
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($matches->count() !== 1) {
                return null;
            }

            $user = $matches->first();

            if (!$user instanceof User) {
                return null;
            }

            $existing = DB::table('phone_password_reset_tokens')
                ->where('user_id', $user->id)
                ->first();

            $resendAfter = (int) config('identity.phone_password_reset.resend_after_seconds');
            if ($existing !== null && $existing->created_at !== null
                && CarbonImmutable::parse($existing->created_at)->addSeconds($resendAfter)->isFuture()) {
                return null;
            }

            $digits = (int) config('identity.phone_password_reset.otp_digits');
            $rawOtp = str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
            $expiresAt = CarbonImmutable::now('UTC')
                ->addMinutes((int) config('identity.phone_password_reset.ttl_minutes'));

            DB::table('phone_password_reset_tokens')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'organization_id' => $user->organization_id,
                    'token_hash' => Hash::make($rawOtp),
                    'attempts' => 0,
                    'expires_at' => $expiresAt,
                    'created_at' => now()->utc(),
                    'updated_at' => now()->utc(),
                ],
            );

            return [
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'phone' => $normalizedPhone,
                'otp' => $rawOtp,
                'expires_at' => $expiresAt,
            ];
        });

        if ($issued !== null) {
            try {
                $this->delivery->deliver(
                    userId: $issued['user_id'],
                    organizationId: $issued['organization_id'],
                    phone: $issued['phone'],
                    otp: $issued['otp'],
                    expiresAt: $issued['expires_at'],
                );
            } catch (Throwable $exception) {
                logger()->warning('Phone password reset delivery failed.', [
                    'user_id' => $issued['user_id'],
                    'organization_id' => $issued['organization_id'],
                    'exception' => $exception::class,
                ]);
            }

            Event::dispatch(new PhonePasswordResetRequested(
                userId: $issued['user_id'],
                organizationId: $issued['organization_id'],
                expiresAt: $issued['expires_at'],
            ));
        }
    }

    public static function normalizePhone(string $phone): ?string
    {
        $normalized = preg_replace('/[\s().-]+/', '', trim($phone));

        return is_string($normalized) && preg_match('/^\+[1-9]\d{7,14}$/', $normalized) === 1
            ? $normalized
            : null;
    }
}

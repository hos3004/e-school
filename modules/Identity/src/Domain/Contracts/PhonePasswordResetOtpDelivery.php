<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts;

use Carbon\CarbonImmutable;
use SensitiveParameter;

/**
 * منفذ تسليم OTP اللحظي. التنفيذ لا يجوز أن يسجل الرمز أو يخزنه خامًا.
 * Task 04 تملك ربطه بقناة WhatsApp/SMS الفعلية.
 */
interface PhonePasswordResetOtpDelivery
{
    public function deliver(
        string $userId,
        string $organizationId,
        string $phone,
        #[SensitiveParameter] string $otp,
        CarbonImmutable $expiresAt,
    ): void;
}

<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Delivery;

use Carbon\CarbonImmutable;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use SensitiveParameter;

/**
 * تنفيذ صريح بلا نقل خارجي إلى أن تربط Task 04 القناة المعتمدة.
 */
final class NullPhonePasswordResetOtpDelivery implements PhonePasswordResetOtpDelivery
{
    public function deliver(
        string $userId,
        string $organizationId,
        string $phone,
        #[SensitiveParameter] string $otp,
        CarbonImmutable $expiresAt,
    ): void {}
}

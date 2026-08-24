<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Events;

use Carbon\CarbonImmutable;
use Shared\Domain\DomainEvent;

/**
 * طُلب رمز OTP لإعادة تعيين كلمة المرور عبر الهاتف.
 *
 * لا يحمل الحدث الهاتف أو الرمز الخام حتى يظل آمنًا عند التسجيل/التسلسل.
 * تسليم الرمز يمر لحظيًا عبر PhonePasswordResetOtpDelivery الذي ستربطه Task 04.
 */
final class PhonePasswordResetRequested extends DomainEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
        public readonly CarbonImmutable $expiresAt,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'identity.phone_password_reset_requested';
    }

    public function module(): string
    {
        return 'Identity';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Support;

use Carbon\CarbonImmutable;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use RuntimeException;
use SensitiveParameter;

final class FakePhonePasswordResetOtpDelivery implements PhonePasswordResetOtpDelivery
{
    /** @var list<array{user_id: string, organization_id: string, phone: string, otp: string, expires_at: CarbonImmutable}> */
    public array $deliveries = [];

    public bool $shouldFail = false;

    public function deliver(
        string $userId,
        string $organizationId,
        string $phone,
        #[SensitiveParameter] string $otp,
        CarbonImmutable $expiresAt,
    ): void {
        if ($this->shouldFail) {
            throw new RuntimeException('fake-delivery-failure');
        }

        $this->deliveries[] = [
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'phone' => $phone,
            'otp' => $otp,
            'expires_at' => $expiresAt,
        ];
    }
}

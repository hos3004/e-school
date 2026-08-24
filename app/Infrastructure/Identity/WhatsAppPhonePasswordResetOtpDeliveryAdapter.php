<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use RuntimeException;
use SensitiveParameter;

/**
 * Application composition adapter: Identity owns the delivery port while
 * Integrations owns the channel DTO. Neither module imports the other.
 */
final readonly class WhatsAppPhonePasswordResetOtpDeliveryAdapter implements PhonePasswordResetOtpDelivery
{
    public function __construct(
        private ChannelGateway $gateway,
    ) {}

    public function deliver(
        string $userId,
        string $organizationId,
        string $phone,
        #[SensitiveParameter] string $otp,
        CarbonImmutable $expiresAt,
    ): void {
        $profile = DB::table('users')
            ->where('id', $userId)
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first(['locale', 'phone_country']);

        if ($profile === null) {
            throw new RuntimeException('phone_password_reset_recipient_unavailable');
        }

        $messageId = (string) Str::ulid();
        $result = $this->gateway->send(new GatewayMessage(
            messageId: $messageId,
            organizationId: $organizationId,
            recipientId: $userId,
            category: 'password_reset_otp',
            channel: 'whatsapp',
            locale: is_string($profile->locale ?? null) && $profile->locale !== ''
                ? str_replace('-', '_', $profile->locale)
                : (string) config('app.fallback_locale', 'ar'),
            eventName: 'identity.phone_password_reset_requested',
            eventId: $messageId,
            correlationId: null,
            subject: null,
            body: [],
            payload: [
                'phone' => $phone,
                'phone_country' => is_string($profile->phone_country ?? null)
                    ? $profile->phone_country
                    : null,
                'provider_template_name' => (string) config(
                    'notifications.password_reset.whatsapp_template',
                    'password_reset_otp',
                ),
                'template_parameters' => [
                    $otp,
                    $expiresAt->utc()->toIso8601String(),
                ],
            ],
        ));

        if (!$result->isAccepted()) {
            throw new RuntimeException('phone_password_reset_delivery_rejected');
        }
    }
}

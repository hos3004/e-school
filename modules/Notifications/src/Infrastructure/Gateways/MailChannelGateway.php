<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Gateways;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\SentMessage;
use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Modules\Notifications\Application\Services\TemplateRenderer;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Infrastructure\Mail\NotificationMail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * بوابة Laravel Mail المحايدة عن المزوّد؛ إعداد mailer وحده يقرر SMTP/SES.
 */
final readonly class MailChannelGateway implements ChannelGateway
{
    public function __construct(
        private Mailer $mailer,
        private TemplateRenderer $templates,
    ) {}

    public function send(GatewayMessage $message): GatewayResult
    {
        if ($message->channel !== Channel::Email->value) {
            return GatewayResult::rejected(
                (string) __('notifications::errors.gateway_channel_mismatch', [
                    'expected' => Channel::Email->label(),
                    'actual' => $message->channel,
                ]),
                false,
            );
        }

        $email = $message->payload['email'] ?? null;

        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return GatewayResult::rejected(
                (string) __('notifications::errors.email_recipient_invalid'),
                false,
            );
        }

        try {
            $mail = new NotificationMail($message, $this->templates);
            $sent = $this->mailer
                ->to($email)
                ->locale($mail->renderedLocale())
                ->send($mail);
        } catch (TransportExceptionInterface $error) {
            return GatewayResult::rejected(
                (string) __('notifications::errors.mail_transport_failed'),
                true,
                ['exception' => $error::class],
            );
        } catch (Throwable $error) {
            return GatewayResult::rejected(
                $error->getMessage(),
                false,
                ['exception' => $error::class],
            );
        }

        $externalId = $sent instanceof SentMessage
            ? (string) $sent->getMessageId()
            : null;

        return GatewayResult::accepted(array_filter([
            'driver' => Channel::Email->value,
            'external_message_id' => $externalId,
            'status' => 'accepted',
        ], static fn (mixed $value): bool => $value !== null));
    }
}

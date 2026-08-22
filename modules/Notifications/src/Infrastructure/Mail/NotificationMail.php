<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Notifications\Application\Services\TemplateRenderer;
use Modules\Notifications\Domain\Enums\Channel;

/**
 * بريد مبني من قالب قاعدة البيانات في لغة المستلم.
 */
final class NotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @var array{
     *     subject: string|null,
     *     body: string,
     *     locale: string,
     *     provider_template_name: string|null,
     *     template_parameters: list<string>
     * }
     */
    private array $rendered;

    public function __construct(
        public readonly GatewayMessage $gatewayMessage,
        TemplateRenderer $templates,
    ) {
        $this->rendered = $templates->render(
            eventKey: $gatewayMessage->eventName,
            channel: Channel::Email->value,
            locale: $gatewayMessage->locale,
            organizationId: $gatewayMessage->organizationId,
            payload: is_array($gatewayMessage->payload['template_data'] ?? null)
                ? $gatewayMessage->payload['template_data']
                : $gatewayMessage->payload,
        );

        $this->locale($this->rendered['locale']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rendered['subject'] ?? '');
    }

    public function content(): Content
    {
        return new Content(
            view: 'notifications::mail.notification',
            with: [
                'body' => $this->rendered['body'],
                'locale' => $this->rendered['locale'],
            ],
        );
    }

    public function renderedSubject(): string
    {
        return $this->rendered['subject'] ?? '';
    }

    public function renderedBody(): string
    {
        return $this->rendered['body'];
    }

    public function renderedLocale(): string
    {
        return $this->rendered['locale'];
    }
}

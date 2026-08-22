<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\ValueObjects;

/**
 * حمولة قناة محايدة عن الموديولات ومكوّنة من قيم أولية فقط.
 */
final readonly class GatewayMessage
{
    /**
     * @param array<string, mixed>|null $subject
     * @param array<string, mixed> $body
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $messageId,
        public string $organizationId,
        public string $recipientId,
        public string $category,
        public string $channel,
        public string $locale,
        public string $eventName,
        public string $eventId,
        public ?string $correlationId,
        public ?array $subject,
        public array $body,
        public array $payload,
    ) {}
}

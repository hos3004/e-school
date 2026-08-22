<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;

/**
 * حدث موحّد قادم من المزوّد عبر webhook — بعد التحقق من التوقيع.
 */
final readonly class WebhookEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public ClassroomEventType $type,
        public string $externalId,
        public ?string $externalUserId,
        public CarbonImmutable $occurredAt,
        public array $payload = [],
    ) {}
}

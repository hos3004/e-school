<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\ValueObjects;

final readonly class ManualNotificationDispatchResult
{
    /** @param array<string, int> $statusCounts */
    public function __construct(
        public int $recipientCount,
        public int $queuedCount,
        public int $suppressedCount,
        public int $skippedCount,
        public bool $alreadyProcessed,
        public array $statusCounts,
    ) {}
}

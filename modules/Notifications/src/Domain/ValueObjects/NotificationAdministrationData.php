<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\ValueObjects;

final readonly class NotificationAdministrationData
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $category,
        public string $channel,
        public string $status,
        public int $attempts,
        public string $scheduledFor,
        public ?string $sentAt,
        public ?string $readAt,
        public ?string $lastError,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\ValueObjects;

use Modules\Notifications\Domain\Enums\ManualRecipientType;

final readonly class ManualRecipientResolution
{
    /** @param list<string> $userIds */
    public function __construct(
        public ManualRecipientType $type,
        public string $targetId,
        public string $label,
        public array $userIds,
    ) {}

    public function count(): int
    {
        return count($this->userIds);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\ValueObjects;

final readonly class GroupMemberData
{
    public function __construct(
        public string $membershipId,
        public string $studentProfileId,
        public string $status,
        public ?string $joinedAt,
        public ?string $leftAt,
    ) {}
}

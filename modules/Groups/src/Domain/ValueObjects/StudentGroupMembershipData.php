<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\ValueObjects;

final readonly class StudentGroupMembershipData
{
    /** @param array<string, string> $groupName */
    public function __construct(
        public string $membershipId,
        public string $groupId,
        public string $groupCode,
        public array $groupName,
        public string $groupStatus,
        public string $membershipStatus,
        public ?string $joinedAt,
        public ?string $leftAt,
    ) {}
}

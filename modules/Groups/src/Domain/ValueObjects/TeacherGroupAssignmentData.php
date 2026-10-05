<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\ValueObjects;

final readonly class TeacherGroupAssignmentData
{
    /** @param array<string, string> $groupName */
    public function __construct(
        public string $assignmentId,
        public string $staffProfileId,
        public string $groupId,
        public string $groupCode,
        public array $groupName,
        public string $groupStatus,
        public ?string $courseId,
        public string $role,
        public ?string $assignedFrom,
        public ?string $assignedTo,
    ) {}
}

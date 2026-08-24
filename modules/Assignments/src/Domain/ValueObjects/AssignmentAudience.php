<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\ValueObjects;

/**
 * Primitive read model returned across the Assignments boundary.
 */
final readonly class AssignmentAudience
{
    /**
     * @param list<string> $activeGroupIds
     * @param list<string> $activeCourseIds
     */
    public function __construct(
        public ?string $studentProfileId,
        public ?string $staffProfileId,
        public array $activeGroupIds,
        public array $activeCourseIds,
    ) {}

    public function targetsStudent(string $courseId, ?string $groupId): bool
    {
        if ($this->studentProfileId === null) {
            return false;
        }

        return $groupId === null
            ? in_array($courseId, $this->activeCourseIds, true)
            : in_array($groupId, $this->activeGroupIds, true);
    }
}

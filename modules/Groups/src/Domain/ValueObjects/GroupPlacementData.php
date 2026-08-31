<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\ValueObjects;

final readonly class GroupPlacementData
{
    /**
     * @param list<array{staff_profile_id: string, course_id: string|null}> $teacherAssignments
     * @param string $membershipStatus قيمة `MembershipStatus` — `pending` داخل مجموعة قيد التخطيط
     */
    public function __construct(
        public string $membershipId,
        public string $groupId,
        public string $organizationId,
        public string $programId,
        public ?string $courseId,
        public string $studentProfileId,
        public array $teacherAssignments,
        public bool $created,
        public string $membershipStatus,
    ) {}
}

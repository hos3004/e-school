<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\ValueObjects;

/** بيانات المجموعة اللازمة لبناء قالب جدول دون تسريب نموذج Eloquent. */
final readonly class SchedulingGroupData
{
    /**
     * @param array<string, string> $name
     * @param list<string> $programIds
     * @param list<TeacherGroupAssignmentData> $teacherAssignments
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public string $status,
        public string $timezone,
        public ?string $startsOn,
        public ?string $endsOn,
        public array $programIds,
        public array $teacherAssignments,
    ) {}
}

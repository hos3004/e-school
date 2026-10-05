<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Events;

use Shared\Domain\DomainEvent;

/** طلب المعلم تغيير الموعد الدائم — يُخطر الطلاب والمشرف والإدارة. */
final class ScheduleChangeRequested extends DomainEvent
{
    /**
     * @param list<string> $studentUserIds
     * @param array<string, string> $courseName
     * @param string|array<string, string> $targetName
     * @param array<string, string> $currentSchedule
     * @param array<string, string> $proposedSchedule
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $scheduleId,
        public readonly string $organizationId,
        public readonly string $staffProfileId,
        public readonly string $courseId,
        public readonly array $studentUserIds,
        public readonly ?string $teacherUserId,
        public readonly array $courseName,
        public readonly string|array $targetName,
        public readonly string $teacherName,
        public readonly array $currentSchedule,
        public readonly array $proposedSchedule,
        public readonly string $timezone,
        public readonly string $expiresAt,
        public readonly int $studentsCount,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'scheduling.schedule_change_requested';
    }

    public function module(): string
    {
        return 'Scheduling';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'schedule_change_request_id' => $this->requestId,
            'schedule_id' => $this->scheduleId,
            'organization_id' => $this->organizationId,
            'staff_profile_id' => $this->staffProfileId,
            'course_id' => $this->courseId,
            'student_user_ids' => $this->studentUserIds,
            'teacher_user_id' => $this->teacherUserId,
            'course_name' => $this->courseName,
            'target_name' => $this->targetName,
            'teacher_name' => $this->teacherName,
            'current_schedule' => $this->currentSchedule,
            'proposed_schedule' => $this->proposedSchedule,
            'timezone' => $this->timezone,
            'expires_at' => $this->expiresAt,
            'students_count' => $this->studentsCount,
        ];
    }
}

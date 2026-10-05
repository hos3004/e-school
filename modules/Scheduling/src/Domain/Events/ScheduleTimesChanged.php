<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * تغيّرت مواعيد جدول قائم فأُعيد توليد حصصه.
 *
 * الحدث يحمل ملخّص الجدول بعد التغيير ومستقبليه، لأن تعديل جدول واحد يُعيد
 * توليد عشرات الحصص، والمطلوب رسالة واحدة تقول المواعيد الجديدة — لا رسالة
 * عن كل حصة مولّدة.
 */
final class ScheduleTimesChanged extends DomainEvent
{
    /**
     * @param list<string> $studentUserIds
     * @param array<string, string> $courseName
     * @param string|array<string, string> $targetName
     * @param list<string> $scheduleTimes
     * @param array<string, string> $weeklyPattern
     */
    public function __construct(
        public readonly string $scheduleId,
        public readonly string $organizationId,
        public readonly string $staffProfileId,
        public readonly string $courseId,
        public readonly string $rrule,
        public readonly array $studentUserIds,
        public readonly ?string $teacherUserId,
        public readonly array $courseName,
        public readonly string $courseCode,
        public readonly string|array $targetName,
        public readonly string $teacherName,
        public readonly int $durationMinutes,
        public readonly int $sessionCount,
        public readonly array $scheduleTimes,
        public readonly string $timezone,
        public readonly array $weeklyPattern = [],
        public readonly string $effectiveFrom = '',
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'scheduling.schedule_times_changed';
    }

    public function module(): string
    {
        return 'Scheduling';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'organization_id' => $this->organizationId,
            'staff_profile_id' => $this->staffProfileId,
            'course_id' => $this->courseId,
            'rrule' => $this->rrule,
            'student_user_ids' => $this->studentUserIds,
            'teacher_user_id' => $this->teacherUserId,
            'course_name' => $this->courseName,
            'course_code' => $this->courseCode,
            'target_name' => $this->targetName,
            'teacher_name' => $this->teacherName,
            'duration_minutes' => $this->durationMinutes,
            'session_count' => $this->sessionCount,
            'schedule_times' => $this->scheduleTimes,
            'timezone' => $this->timezone,
            'weekly_pattern' => $this->weeklyPattern,
            'effective_from' => $this->effectiveFrom,
        ];
    }
}

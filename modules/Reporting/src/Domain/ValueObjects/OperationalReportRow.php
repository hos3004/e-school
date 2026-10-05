<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\ValueObjects;

final readonly class OperationalReportRow
{
    /**
     * @param list<array{id: string, name: string, attendance_status: string, attendance_label: string, attended_minutes: int}> $students
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $scheduledStart,
        public string $scheduledEnd,
        public string $scheduledStartDisplay,
        public string $scheduledEndDisplay,
        public int $durationMinutes,
        public ?int $actualDurationMinutes,
        public string $courseId,
        public string $course,
        public string $groupId,
        public string $group,
        public string $actualTeacherId,
        public string $actualTeacher,
        public string $originalTeacherId,
        public string $originalTeacher,
        public bool $hasSubstitute,
        public array $students,
        public string $studentsDisplay,
        public string $attendanceSummary,
        public int $presentCount,
        public int $absentCount,
        public string $status,
        public string $statusLabel,
        public string $statusColor,
        public string $sessionType,
        public string $sessionTypeLabel,
        public ?string $cancellationReason,
        public string $reportStatus,
        public string $reportStatusLabel,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'scheduled_start' => $this->scheduledStart,
            'scheduled_end' => $this->scheduledEnd,
            'scheduled_start_display' => $this->scheduledStartDisplay,
            'scheduled_end_display' => $this->scheduledEndDisplay,
            'duration_minutes' => $this->durationMinutes,
            'actual_duration_minutes' => $this->actualDurationMinutes,
            'course_id' => $this->courseId,
            'course' => $this->course,
            'group_id' => $this->groupId,
            'group' => $this->group,
            'actual_teacher_id' => $this->actualTeacherId,
            'actual_teacher' => $this->actualTeacher,
            'original_teacher_id' => $this->originalTeacherId,
            'original_teacher' => $this->originalTeacher,
            'has_substitute' => $this->hasSubstitute,
            'students' => $this->students,
            'students_display' => $this->studentsDisplay,
            'attendance_summary' => $this->attendanceSummary,
            'present_count' => $this->presentCount,
            'absent_count' => $this->absentCount,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'status_color' => $this->statusColor,
            'session_type' => $this->sessionType,
            'session_type_label' => $this->sessionTypeLabel,
            'cancellation_reason' => $this->cancellationReason,
            'report_status' => $this->reportStatus,
            'report_status_label' => $this->reportStatusLabel,
        ];
    }
}

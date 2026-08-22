<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\ValueObjects;

/**
 * صف لوحة الطالب للقراءة — DTO مسطّح لا يحمل Eloquent.
 *
 * هذا هو الشكل الوحيد الذي يخرج من موديول Reporting للاستهلاك الخارجي
 * عبر Query Service.
 */
final readonly class StudentDashboardRow
{
    public function __construct(
        public string $enrollmentId,
        public string $studentProfileId,
        public int $sessionsAttended,
        public int $sessionsMissed,
        public int $attendanceRateBp,
        public int $violationsCount,
        public int $freezesCount,
        public ?string $lastSessionAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enrollment_id' => $this->enrollmentId,
            'student_profile_id' => $this->studentProfileId,
            'sessions_attended' => $this->sessionsAttended,
            'sessions_missed' => $this->sessionsMissed,
            'attendance_rate_bp' => $this->attendanceRateBp,
            'violations_count' => $this->violationsCount,
            'freezes_count' => $this->freezesCount,
            'last_session_at' => $this->lastSessionAt,
        ];
    }
}

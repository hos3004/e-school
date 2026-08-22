<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Events;

/**
 * قدّم المعلم تقرير الحصة — يستمع إليه باقي الموديولات (إشعارات، تدقيق).
 */
final class SessionReportSubmitted extends AcademicReportsEvent
{
    /**
     * @param list<string> $studentProfileIds
     * @param array<string, int> $lateMinutesByRule محجوز للتوسعة — فارغ افتراضيًا
     */
    public function __construct(
        public readonly string $sessionReportId,
        public readonly string $sessionId,
        public readonly string $staffProfileId,
        public readonly bool $isLate,
        public readonly int $studentCount,
        public readonly array $studentProfileIds,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academicreports.session_report_submitted';
    }

    public function payload(): array
    {
        return [
            'session_report_id' => $this->sessionReportId,
            'session_id' => $this->sessionId,
            'staff_profile_id' => $this->staffProfileId,
            'is_late' => $this->isLate,
            'student_count' => $this->studentCount,
            'student_profile_ids' => $this->studentProfileIds,
        ];
    }
}

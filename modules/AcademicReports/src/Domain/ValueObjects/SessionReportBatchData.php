<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\ValueObjects;

/**
 * تقرير حصة واحد مع تقييمات طلابه، لقراءة مجمّعة عابرة للموديولات.
 *
 * لا تحمل `supervisor_private_note` — تلك بيانات إشرافية داخلية فقط.
 */
final readonly class SessionReportBatchData
{
    /** @param list<SessionReportStudentEntryData> $students */
    public function __construct(
        public string $sessionId,
        public string $staffProfileId,
        public string $submittedAt,
        public bool $isLate,
        public ?string $topicsCovered,
        public ?string $homeworkAssigned,
        public ?string $generalNotes,
        public ?string $nextSessionPlan,
        public array $students,
    ) {}
}

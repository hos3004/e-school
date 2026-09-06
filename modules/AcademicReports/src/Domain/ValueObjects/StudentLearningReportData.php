<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\ValueObjects;

/** Educational fields only. Supervisor-private and whole-class notes cannot enter this DTO. */
final readonly class StudentLearningReportData
{
    public function __construct(
        public string $id, public string $sessionId, public string $submittedAt,
        public ?int $participation, public ?int $performance, public ?int $commitment,
        public ?string $strengths, public ?string $weaknesses, public ?string $note,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\ValueObjects;

final readonly class SessionReportStudentEntryData
{
    public function __construct(
        public string $studentProfileId,
        public ?int $participation,
        public ?int $performance,
        public ?int $commitment,
        public ?string $strengths,
        public ?string $weaknesses,
        public ?string $note,
    ) {}
}

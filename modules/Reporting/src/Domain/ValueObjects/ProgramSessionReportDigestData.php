<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\ValueObjects;

/**
 * سطر تقرير حصة واحد داخل تجميع البرنامج — طالب واحد لكل سطر.
 */
final readonly class ProgramSessionReportDigestData
{
    public function __construct(
        public string $programId,
        public string $studentProfileId,
        public string $sessionId,
        public string $submittedAt,
        public ?string $topicsCovered,
        public ?string $homeworkAssigned,
        public ?string $generalNotes,
        public ?int $participation,
        public ?int $performance,
        public ?int $commitment,
        public ?string $strengths,
        public ?string $weaknesses,
        public ?string $note,
    ) {}
}

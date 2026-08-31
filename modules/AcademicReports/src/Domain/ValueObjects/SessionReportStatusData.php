<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\ValueObjects;

final readonly class SessionReportStatusData
{
    public function __construct(
        public string $sessionId,
        public ?string $submittedAt,
        public bool $isLate,
    ) {}

    public function state(): string
    {
        if ($this->submittedAt === null) {
            return 'missing';
        }

        return $this->isLate ? 'late' : 'submitted';
    }
}

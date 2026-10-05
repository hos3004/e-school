<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\ValueObjects;

final readonly class EnrollmentSummaryData
{
    public function __construct(
        public string $id,
        public string $programId,
        public string $status,
        public ?string $currentLevelId,
        public ?string $appliedAt,
        public ?string $activatedAt,
        public ?string $completedAt,
        public ?string $expectedReturnDate,
    ) {}
}

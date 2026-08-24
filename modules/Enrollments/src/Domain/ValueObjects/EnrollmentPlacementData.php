<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\ValueObjects;

final readonly class EnrollmentPlacementData
{
    public function __construct(
        public string $enrollmentId,
        public string $organizationId,
        public string $studentProfileId,
        public string $programId,
        public string $status,
        public bool $created,
    ) {}
}

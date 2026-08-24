<?php

declare(strict_types=1);

namespace Modules\Students\Domain\ValueObjects;

final readonly class StudentPlacementData
{
    public function __construct(
        public string $applicationId,
        public string $organizationId,
        public string $studentProfileId,
        public string $studentUserId,
        public string $status,
        public ?string $dateOfBirth,
        public ?string $gender,
        public ?string $countryId,
        public ?string $regionId,
    ) {}
}

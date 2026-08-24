<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class ApplicantFacts
{
    public function __construct(
        public ?CarbonImmutable $dateOfBirth = null,
        public ?string $gender = null,
        public ?string $countryId = null,
        public ?string $regionId = null,
    ) {}

    public function age(CarbonImmutable $at = new CarbonImmutable): ?int
    {
        $age = $this->dateOfBirth?->diffInYears($at);

        return $age === null ? null : (int) $age;
    }
}

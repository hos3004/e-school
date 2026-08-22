<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\ValueObjects;

final readonly class ProgramEligibilityData
{
    /**
     * @param list<string> $countries
     * @param list<string> $regions
     */
    public function __construct(
        public string $programId,
        public array $countries = [],
        public array $regions = [],
        public ?int $ageFrom = null,
        public ?int $ageTo = null,
        public ?string $gender = null,
        public bool $manualApprovalRequired = true,
        public string $teacherGenderRule = 'any',
        public bool $requiresIndividualSessions = false,
    ) {}
}

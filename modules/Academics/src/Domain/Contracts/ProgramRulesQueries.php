<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Contracts;

use Modules\Academics\Domain\ValueObjects\ProgramEligibilityData;

interface ProgramRulesQueries
{
    public function eligibilityOf(string $programId): ?ProgramEligibilityData;

    /** @return 'same'|'any' */
    public function teacherGenderRule(string $programId): string;

    /** @return list<string> */
    public function programIdsOfCourse(string $courseId): array;

    public function sessionModeOfCourse(string $courseId): ?string;
}

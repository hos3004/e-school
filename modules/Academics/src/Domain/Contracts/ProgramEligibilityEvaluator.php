<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Contracts;

use Modules\Academics\Domain\ValueObjects\ApplicantFacts;
use Modules\Academics\Domain\ValueObjects\EligibilityResult;

interface ProgramEligibilityEvaluator
{
    public function evaluate(string $programId, ApplicantFacts $facts): EligibilityResult;
}

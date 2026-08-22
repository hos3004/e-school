<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Services;

use Modules\Academics\Domain\Models\ProgramEligibility;
use Modules\Academics\Domain\ValueObjects\ApplicantFacts;
use Modules\Academics\Domain\ValueObjects\EligibilityResult;

final class EligibilityEvaluator
{
    public function evaluate(string $programId, ApplicantFacts $facts): EligibilityResult
    {
        /** @var ProgramEligibility|null $eligibility */
        $eligibility = ProgramEligibility::query()
            ->where('program_id', $programId)
            ->first();

        // No eligibility rules defined = eligible by default
        if ($eligibility === null) {
            return new EligibilityResult(
                eligible: true,
                violations: [],
                requiresManualApproval: false,
                blocking: [],
                warnings: [],
            );
        }

        $violations = [];

        // 1. Country Check (Empty list = no restriction)
        $countries = is_array($eligibility->countries) ? array_values($eligibility->countries) : [];
        if (! empty($countries) && ($facts->countryId === null || ! in_array($facts->countryId, $countries, true))) {
            $violations[] = 'eligibility.country_not_allowed';
        }

        // 2. Region Check (Empty list = no restriction)
        $regions = is_array($eligibility->regions) ? array_values($eligibility->regions) : [];
        if (! empty($regions) && ($facts->regionId === null || ! in_array($facts->regionId, $regions, true))) {
            $violations[] = 'eligibility.region_not_allowed';
        }

        // 3. Age Range Check
        $ageFrom = $eligibility->age_from;
        $ageTo = $eligibility->age_to;
        if ($ageFrom !== null || $ageTo !== null) {
            $age = $facts->age();
            if ($age === null) {
                $violations[] = 'eligibility.age_out_of_range';
            } else {
                if ($ageFrom !== null && $age < $ageFrom) {
                    $violations[] = 'eligibility.age_out_of_range';
                }
                if ($ageTo !== null && $age > $ageTo) {
                    $violations[] = 'eligibility.age_out_of_range';
                }
            }
        }

        // 4. Gender Check
        $targetGender = $eligibility->gender?->value;
        if ($targetGender !== null && $targetGender !== 'all' && $facts->gender !== $targetGender) {
            $violations[] = 'eligibility.gender_mismatch';
        }

        // Categorize violations into blocking and warnings using config
        $blocking = [];
        $warnings = [];

        foreach ($violations as $violation) {
            $severity = config("admission.eligibility.on_violation.{$violation}", 'block');
            if ($severity === 'warn') {
                $warnings[] = $violation;
            } else {
                $blocking[] = $violation;
            }
        }

        return new EligibilityResult(
            eligible: empty($blocking),
            violations: $violations,
            requiresManualApproval: $eligibility->manual_approval_required,
            blocking: array_values(array_unique($blocking)),
            warnings: array_values(array_unique($warnings)),
        );
    }
}

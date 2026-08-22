<?php

declare(strict_types=1);

namespace Modules\Assessments\Application\Concerns;

use Carbon\CarbonImmutable;
use Modules\Assessments\Domain\Models\Assessment;
use Shared\Support\BusinessRuleViolation;

/**
 * حراس قواعد العمل المشتركة بين إجراءات إنشاء وتعديل الاختبارات.
 */
trait ValidatesAssessmentRules
{
    private function guardScoreConsistency(int $totalScore, int $passingScore): void
    {
        if ($passingScore > $totalScore) {
            throw BusinessRuleViolation::make(
                'assessments.passing_score_above_total',
                'assessments::errors.passing_score_above_total',
            );
        }
    }

    private function guardAvailabilityWindow(Assessment $assessment): void
    {
        $this->guardAvailabilityWindowValues($assessment->available_from, $assessment->available_to);
    }

    private function guardAvailabilityWindowValues(mixed $availableFrom, mixed $availableTo): void
    {
        $from = CarbonImmutable::parse($availableFrom, 'UTC');
        $to = CarbonImmutable::parse($availableTo, 'UTC');

        if ($from->gte($to)) {
            throw BusinessRuleViolation::make(
                'assessments.invalid_availability_window',
                'assessments::errors.invalid_availability_window',
            );
        }
    }

    private function guardWithinAvailabilityWindow(Assessment $assessment): void
    {
        $now = now('UTC');

        if ($now->lt($assessment->available_from) || $now->gt($assessment->available_to)) {
            throw BusinessRuleViolation::make(
                'assessments.outside_availability_window',
                'assessments::errors.outside_availability_window',
            );
        }
    }
}

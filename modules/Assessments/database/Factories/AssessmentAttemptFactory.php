<?php

declare(strict_types=1);

namespace Modules\Assessments\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<AssessmentAttempt>
 */
final class AssessmentAttemptFactory extends Factory
{
    protected $model = AssessmentAttempt::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'student_profile_id' => Fixtures::studentProfileId(),
            'reactivation_request_id' => null,
            'attempt_number' => 1,
            'started_at' => CarbonImmutable::now('UTC')->subHour(),
            'submitted_at' => null,
            'score' => null,
            'passed' => null,
            'graded_by' => null,
            'graded_at' => null,
            'answers' => [],
            'created_at' => CarbonImmutable::now('UTC'),
        ];
    }

    /** محاولة مُسلَّمة بإجابات لكن بلا تصحيح. */
    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'submitted_at' => CarbonImmutable::now('UTC'),
            'answers' => [
                'q1' => ['key' => 'a'],
                'q2' => ['text' => 'إجابة نصية تجريبية'],
            ],
        ]);
    }

    /** محاولة مُصحَّحة نهائيًا. */
    public function graded(int $score, bool $passed): static
    {
        return $this->state(fn (): array => [
            'submitted_at' => CarbonImmutable::now('UTC')->subMinutes(30),
            'score' => $score,
            'passed' => $passed,
            'graded_by' => Fixtures::userId(),
            'graded_at' => CarbonImmutable::now('UTC')->subMinutes(20),
        ]);
    }
}

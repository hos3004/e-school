<?php

declare(strict_types=1);

namespace Modules\Assignments\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<AssignmentSubmission>
 */
final class AssignmentSubmissionFactory extends Factory
{
    protected $model = AssignmentSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'student_profile_id' => Fixtures::studentProfileId(),
            'submitted_at' => null,
            'is_late' => false,
            'content' => null,
            'attachments' => [],
            'score' => null,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
            'status' => AssignmentSubmissionStatus::Pending,
        ];
    }

    /** تسليم في الموعد. */
    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'submitted_at' => CarbonImmutable::now('UTC')->subDay(),
            'is_late' => false,
            'content' => $this->faker->paragraph(),
            'status' => AssignmentSubmissionStatus::Submitted,
        ]);
    }

    /** تسليم متأخر. */
    public function late(): static
    {
        return $this->state(fn (): array => [
            'submitted_at' => CarbonImmutable::now('UTC'),
            'is_late' => true,
            'content' => $this->faker->paragraph(),
            'status' => AssignmentSubmissionStatus::Late,
        ]);
    }

    /** تسليم مرصود الدرجة. */
    public function graded(?int $score = null): static
    {
        return $this->state(fn (): array => [
            'submitted_at' => CarbonImmutable::now('UTC')->subDays(2),
            'content' => $this->faker->paragraph(),
            'status' => AssignmentSubmissionStatus::Graded,
        ])->afterMaking(function (AssignmentSubmission $submission) use ($score): void {
            $maxScore = $submission->assignment()->firstOrFail()->max_score;

            $submission->forceFill([
                'score' => $score ?? (int) ($maxScore * 0.8),
                'feedback' => $this->faker->sentence(),
                'graded_by' => Fixtures::userId(),
                'graded_at' => CarbonImmutable::now('UTC'),
            ]);
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Discipline\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<ReactivationRequest>
 */
final class ReactivationRequestFactory extends Factory
{
    protected $model = ReactivationRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'enrollment_id' => (string) str()->ulid(),
            'requested_by' => Fixtures::userId(),
            'status' => ReactivationStatus::Pending,
            'attempt_number' => 1,
            'assessment_attempt_id' => null,
            'student_statement' => 'أتعهد بالحضور المنتظم والتزام مواعيد الحصص.',
            'reviewer_id' => null,
            'reviewed_at' => null,
            'decision_note' => null,
        ];
    }

    /** طلب مقبول بنتيجة اختبار جدية. */
    public function approved(string $reviewerId, string $assessmentAttemptId): static
    {
        return $this->state(fn (): array => [
            'status' => ReactivationStatus::Approved,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now()->toImmutable(),
            'decision_note' => 'نجح الطالب في اختبار الجدية.',
            'assessment_attempt_id' => $assessmentAttemptId,
        ]);
    }

    /** طلب مرفوض بملاحظة. */
    public function rejected(string $reviewerId, string $note = 'لم يستوفِ شروط العودة.'): static
    {
        return $this->state(fn (): array => [
            'status' => ReactivationStatus::Rejected,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now()->toImmutable(),
            'decision_note' => $note,
        ]);
    }
}

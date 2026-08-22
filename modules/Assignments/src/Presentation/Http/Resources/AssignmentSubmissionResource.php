<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assignments\Domain\Models\AssignmentSubmission;

/**
 * تمثيل تسليم في الـ API.
 *
 * @property-read AssignmentSubmission $resource
 */
final class AssignmentSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AssignmentSubmission $submission */
        $submission = $this->resource;

        return [
            'id' => (string) $submission->getKey(),
            'assignment_id' => $submission->assignment_id,
            'student_profile_id' => $submission->student_profile_id,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'is_late' => (bool) $submission->is_late,
            'content' => $submission->content,
            'attachments' => $submission->attachments,
            'score' => $submission->score,
            'feedback' => $submission->feedback,
            'graded_by' => $submission->graded_by,
            'graded_at' => $submission->graded_at?->toIso8601String(),
            'status' => $submission->status?->value,
            'status_label' => $submission->status?->label(),
        ];
    }
}

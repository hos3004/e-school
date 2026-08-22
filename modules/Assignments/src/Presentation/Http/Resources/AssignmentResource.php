<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Assignments\Domain\Models\Assignment;

/**
 * تمثيل نشاط في الـ API.
 *
 * @property-read Assignment $resource
 */
final class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Assignment $assignment */
        $assignment = $this->resource;

        return [
            'id' => (string) $assignment->getKey(),
            'organization_id' => $assignment->organization_id,
            'course_id' => $assignment->course_id,
            'group_id' => $assignment->group_id,
            'staff_profile_id' => $assignment->staff_profile_id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'attachments' => $assignment->attachments,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'due_at' => $assignment->due_at?->toIso8601String(),
            'max_score' => $assignment->max_score,
            'allows_late' => (bool) $assignment->allows_late,
            'late_penalty_percent' => $assignment->late_penalty_percent,
            'is_past_due' => $assignment->isPastDue(),
        ];
    }
}

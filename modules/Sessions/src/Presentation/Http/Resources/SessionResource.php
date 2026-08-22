<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تمثيل الحصة في الـ API.
 *
 * @mixin \Modules\Sessions\Domain\Models\Session
 */
final class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'schedule_id' => $this->schedule_id,
            'group_id' => $this->group_id,
            'course_id' => $this->course_id,
            'staff_profile_id' => $this->staff_profile_id,
            'substitute_for_staff_id' => $this->substitute_for_staff_id,
            'makeup_for_session_id' => $this->makeup_for_session_id,
            'session_type' => $this->session_type,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'scheduled_start' => $this->scheduled_start?->toIso8601String(),
            'scheduled_end' => $this->scheduled_end?->toIso8601String(),
            'actual_start' => $this->actual_start?->toIso8601String(),
            'actual_end' => $this->actual_end?->toIso8601String(),
            'title' => $this->title,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

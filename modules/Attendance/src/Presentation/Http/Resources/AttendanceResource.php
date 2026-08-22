<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Attendance\Domain\Models\Attendance;

/**
 * تمثيل قيد الحضور في الـ API — قراءة فقط.
 */
final class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Attendance $attendance */
        $attendance = $this->resource;

        return [
            'id' => (string) $attendance->getKey(),
            'session_participant_id' => (string) $attendance->session_participant_id,
            'status' => $attendance->status?->value,
            'status_label' => $attendance->status?->label(),
            'derived_status' => $attendance->derived_status?->value,
            'derived_status_label' => $attendance->derived_status?->label(),
            'attended_minutes' => $attendance->attended_minutes,
            'joined_after_minutes' => $attendance->joined_after_minutes,
            'left_before_minutes' => $attendance->left_before_minutes,
            'is_confirmed' => $attendance->isConfirmed(),
            'confirmed_by' => $attendance->confirmed_by,
            'confirmed_at' => $attendance->confirmed_at?->toIso8601String(),
            'override_reason' => $attendance->override_reason,
            'created_at' => $attendance->created_at?->toIso8601String(),
            'updated_at' => $attendance->updated_at?->toIso8601String(),
        ];
    }
}

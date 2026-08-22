<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reporting\Domain\Models\StudentDashboard;

/**
 * تمثيل لوحة الطالب في الـ API.
 *
 * @mixin StudentDashboard
 */
final class StudentDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'enrollment_id' => $this->enrollment_id,
            'student_profile_id' => $this->student_profile_id,
            'sessions_total' => $this->sessions_total,
            'sessions_attended' => $this->sessions_attended,
            'sessions_missed' => $this->sessions_missed,
            'attendance_rate_bp' => $this->attendance_rate_bp,
            'violations_count' => $this->violations_count,
            'freezes_count' => $this->freezes_count,
            'last_session_at' => $this->last_session_at?->toIso8601String(),
            'last_violation_at' => $this->last_violation_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

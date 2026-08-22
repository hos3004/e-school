<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reporting\Domain\Models\TeacherDashboard;

/**
 * تمثيل لوحة المعلم في الـ API.
 *
 * @mixin TeacherDashboard
 */
final class TeacherDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'staff_profile_id' => $this->staff_profile_id,
            'sessions_total' => $this->sessions_total,
            'sessions_completed' => $this->sessions_completed,
            'cancellations_by_self' => $this->cancellations_by_self,
            'postponements' => $this->postponements,
            'payout_minor' => $this->payout_minor,
            'currency' => $this->currency,
            'last_session_at' => $this->last_session_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

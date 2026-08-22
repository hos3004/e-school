<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;

/**
 * تمثيل اللقطة التنظيمية في الـ API.
 *
 * @mixin OrganizationSnapshot
 */
final class OrganizationSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'snapshot_date' => $this->snapshot_date?->toDateString(),
            'period_type' => [
                'value' => $this->period_type->value,
                'label' => $this->period_type->label(),
            ],
            'students_active' => $this->students_active,
            'students_frozen' => $this->students_frozen,
            'teachers_active' => $this->teachers_active,
            'sessions_held' => $this->sessions_held,
            'sessions_cancelled' => $this->sessions_cancelled,
            'attendance_rate_bp' => $this->attendance_rate_bp,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

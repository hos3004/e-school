<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AcademicReports\Domain\Models\MonthlyReport;

/**
 * تمثيل التقرير الشهري في الـ API.
 *
 * الملاحظة الخاصة بالمشرف على تقرير الحصة لا تُرجع هنا أبدًا — سرّية إشرافية.
 *
 * @mixin MonthlyReport
 */
final class MonthlyReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'student_profile_id' => $this->student_profile_id,
            'enrollment_id' => $this->enrollment_id,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'metrics' => $this->metrics,
            'supervisor_summary' => $this->supervisor_summary,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'approved_at' => $this->approved_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

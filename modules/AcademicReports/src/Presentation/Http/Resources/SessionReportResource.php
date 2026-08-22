<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Modules\AcademicReports\Domain\Models\SessionReport;

/**
 * تمثيل تقرير الحصة في الـ API — مع تقييمات الطلاب.
 *
 * @mixin SessionReport
 */
final class SessionReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'staff_profile_id' => $this->staff_profile_id,
            'topics_covered' => $this->topics_covered,
            'homework_assigned' => $this->homework_assigned,
            'general_notes' => $this->general_notes,
            'next_session_plan' => $this->next_session_plan,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'is_late' => (bool) $this->is_late,
            'students' => $this->whenLoaded(
                'students',
                fn (): Collection => $this->students->map(
                    fn ($student): SessionReportStudentResource => new SessionReportStudentResource($student),
                ),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

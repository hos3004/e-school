<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;

/**
 * تمثيل تقييم طالب داخل تقرير الحصة.
 *
 * @mixin SessionReportStudent
 */
final class SessionReportStudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_report_id' => $this->session_report_id,
            'student_profile_id' => $this->student_profile_id,
            'participation' => $this->participation,
            'performance' => $this->performance,
            'commitment' => $this->commitment,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'note' => $this->note,
        ];
    }
}

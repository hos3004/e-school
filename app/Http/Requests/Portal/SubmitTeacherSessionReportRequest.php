<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;

final class SubmitTeacherSessionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('session_report.create');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $score = ['required', 'integer', 'min:'.SessionReportStudent::MIN_SCORE, 'max:'.SessionReportStudent::MAX_SCORE];

        return [
            'summary' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // المعلم يقرّ أن الحصة انعقدت فعليًا خارج المنصة — يُحوَّلها
            // هذا الإقرار من "مجدولة" إلى "بانتظار المراجعة" فتدخل قائمة
            // اعتماد الإدارة بدل أن تبقى غير مرئية للأبد؛ الاعتماد المالي
            // يبقى قرار الإدارة وحدها، هذا الحقل لا يعتمد شيئًا بنفسه.
            'held_off_platform' => ['sometimes', 'boolean'],
            'students' => ['required', 'array', 'min:1'],
            'students.*.student_profile_id' => ['required', 'string', 'size:26', 'distinct'],
            'students.*.participation' => $score,
            'students.*.performance' => $score,
            'students.*.commitment' => $score,
            'students.*.strengths' => ['nullable', 'string', 'max:2000'],
            'students.*.weaknesses' => ['nullable', 'string', 'max:2000'],
            'students.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\AcademicReports\Application\Policies\SessionReportPolicy;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;

/**
 * طلب تقديم تقرير الحصة.
 */
final class SubmitSessionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(SessionReportPolicy::class)->create($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scoreRange = ['integer', 'min:'.SessionReportStudent::MIN_SCORE, 'max:'.SessionReportStudent::MAX_SCORE];

        return [
            'session_id' => ['required', 'string', 'size:26'],
            'staff_profile_id' => ['required', 'string', 'size:26'],
            'session_ended_at' => ['nullable', 'date'],
            'topics_covered' => ['nullable', 'string', 'max:5000'],
            'homework_assigned' => ['nullable', 'string', 'max:5000'],
            'general_notes' => ['nullable', 'string', 'max:5000'],
            'supervisor_private_note' => ['nullable', 'string', 'max:5000'],
            'next_session_plan' => ['nullable', 'string', 'max:5000'],
            'students' => ['required', 'array', 'min:1'],
            'students.*.student_profile_id' => ['required', 'string', 'size:26', 'distinct'],
            'students.*.participation' => $scoreRange,
            'students.*.performance' => $scoreRange,
            'students.*.commitment' => $scoreRange,
            'students.*.strengths' => ['nullable', 'string', 'max:2000'],
            'students.*.weaknesses' => ['nullable', 'string', 'max:2000'],
            'students.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_id.required' => __('academicreports::validation.session_id_required'),
            'staff_profile_id.required' => __('academicreports::validation.staff_profile_id_required'),
            'students.required' => __('academicreports::validation.students_required'),
            'students.min' => __('academicreports::validation.students_min'),
            'students.*.student_profile_id.distinct' => __('academicreports::validation.students_distinct'),
            'students.*.participation.min' => __('academicreports::validation.score_range'),
            'students.*.participation.max' => __('academicreports::validation.score_range'),
            'students.*.performance.min' => __('academicreports::validation.score_range'),
            'students.*.performance.max' => __('academicreports::validation.score_range'),
            'students.*.commitment.min' => __('academicreports::validation.score_range'),
            'students.*.commitment.max' => __('academicreports::validation.score_range'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\AcademicReports\Application\Policies\MonthlyReportPolicy;

/**
 * طلب توليد تقرير شهري مسوّدة.
 */
final class StoreMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(MonthlyReportPolicy::class)->create($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['prohibited'],
            'student_profile_id' => ['required', 'string', 'size:26'],
            'enrollment_id' => ['required', 'string', 'size:26'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'metrics' => ['nullable', 'array'],
            'supervisor_summary' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_year.required' => __('academicreports::validation.period_year_required'),
            'period_month.between' => __('academicreports::validation.period_month_invalid'),
            'student_profile_id.required' => __('academicreports::validation.student_profile_id_required'),
            'enrollment_id.required' => __('academicreports::validation.enrollment_id_required'),
        ];
    }
}

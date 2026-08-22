<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Domain\Models\MonthlyReport;

/**
 * طلب اعتماد التقرير الشهري — السبب إلزامي (قاعدة التدقيق).
 */
final class ApproveMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = MonthlyReport::query()->find((string) $this->route('report'));

        if ($report === null) {
            return false;
        }

        return Gate::forUser($this->user())->allows('approve', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('academicreports::validation.reason_required'),
            'reason.min' => __('academicreports::validation.reason_min'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\AcademicReports\Domain\Models\MonthlyReport;

/**
 * طلب إرسال التقرير الشهري للطالب ووليّ الأمر.
 */
final class SendMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = MonthlyReport::query()->find((string) $this->route('report'));

        if ($report === null) {
            return false;
        }

        return Gate::forUser($this->user())->allows('send', $report);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

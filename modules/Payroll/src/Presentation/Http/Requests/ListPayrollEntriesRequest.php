<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تصفية قيود المستحقات — معايير اختيارية كلها.
 */
final class ListPayrollEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payroll_period_id' => ['nullable', 'string', 'size:26'],
            'staff_profile_id' => ['nullable', 'string', 'size:26'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب رفض تسوية مقترحة — السبب إلزامي.
 */
final class RejectPayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can((string) config('payroll.adjustments.approve_permission'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('payroll::fields.rejection_reason'),
        ];
    }
}

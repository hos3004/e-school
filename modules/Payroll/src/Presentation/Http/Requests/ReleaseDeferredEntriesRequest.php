<?php

declare(strict_types=1);

namespace Modules\Payroll\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تحرير القيود المؤجَّلة المرتبطة بحصة تلافي أُقيمت.
 */
final class ReleaseDeferredEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.entries.release');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'staff_profile_id' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'staff_profile_id' => __('payroll::fields.staff_profile'),
        ];
    }
}

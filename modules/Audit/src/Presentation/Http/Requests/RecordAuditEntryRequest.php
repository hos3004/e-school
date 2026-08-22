<?php

declare(strict_types=1);

namespace Modules\Audit\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * طلب تسجيل قيدة تدقيق عبر الـ API.
 *
 * شرط «سبب مكتوب للأفعال الحساسة» مرفوض هنا على مستوى الـ FormRequest
 * قبل وصوله للإجراء — القائمة من config لا من الكود.
 */
final class RecordAuditEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('audit.record');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'string', 'size:26'],
            'acting_for_user_id' => ['nullable', 'string', 'size:26'],
            'action' => [
                'required',
                'string',
                'max:128',
                Rule::notIn(['']),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = strtolower(trim((string) $value));

                    if (trim((string) $this->input('reason', '')) !== '') {
                        return;
                    }

                    foreach ((array) config('audit.reason_required_actions', []) as $pattern) {
                        if (Str::is((string) $pattern, $normalized)) {
                            $fail(__('audit::errors.reason_required', ['action' => $normalized]));

                            return;
                        }
                    }
                },
            ],
            'auditable_type' => ['required', 'string', 'max:191'],
            'auditable_id' => ['nullable', 'string', 'size:26'],
            'old_values' => ['nullable', 'array'],
            'new_values' => ['nullable', 'array'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => __('audit::validation.action_required'),
            'action.max' => __('audit::validation.action_max'),
            'auditable_type.required' => __('audit::validation.auditable_type_required'),
            'old_values.array' => __('audit::validation.values_must_be_object'),
            'new_values.array' => __('audit::validation.values_must_be_object'),
            'organization_id.size' => __('audit::validation.must_be_ulid'),
            'auditable_id.size' => __('audit::validation.must_be_ulid'),
            'acting_for_user_id.size' => __('audit::validation.must_be_ulid'),
            'reason.max' => __('audit::validation.reason_too_long'),
        ];
    }
}

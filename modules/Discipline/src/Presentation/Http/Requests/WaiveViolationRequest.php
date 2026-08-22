<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Discipline\Domain\Models\ViolationEvent;

/**
 * طلب العفو عن مخالفة — السبب إلزامي وفق قاعدة التدقيق.
 */
final class WaiveViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ViolationEvent $violation */
        $violation = $this->route('violation');

        return $this->user()->can('waive', $violation);
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
            'reason.required' => __('discipline::validation.reason_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('discipline::attributes.reason'),
        ];
    }
}

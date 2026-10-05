<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب أرشفة برنامج — السبب إلزامي وفق عقد التدقيق.
 */
final class ArchiveProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('program'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.(int) config('academics.reason.minimum_length'), 'max:'.(int) config('academics.reason.maximum_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('academics::validation.reason_required'),
        ];
    }
}

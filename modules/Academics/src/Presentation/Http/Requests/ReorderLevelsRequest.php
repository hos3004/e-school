<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Academics\Domain\Models\Level;

/**
 * طلب إعادة ترتيب مستويات برنامج.
 */
final class ReorderLevelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reorder', Level::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'program_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists('programs', 'id')->where('organization_id', (string) $this->user()->organization_id),
            ],
            'level_ids' => ['required', 'array', 'min:1'],
            'level_ids.*' => [
                'required',
                'string',
                'size:26',
                'distinct',
                Rule::exists('levels', 'id')
                    ->where('program_id', (string) $this->input('program_id')),
            ],
            'reason' => ['required', 'string', 'min:'.(int) config('academics.reason.minimum_length'), 'max:'.(int) config('academics.reason.maximum_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'program_id.exists' => __('academics::errors.program_not_found'),
            'level_ids.*.exists' => __('academics::errors.level_not_in_program'),
        ];
    }
}

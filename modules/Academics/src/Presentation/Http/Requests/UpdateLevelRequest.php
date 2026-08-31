<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تحديث مستوى.
 */
final class UpdateLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('level'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $level = $this->route('level');
        $levelId = (string) $level?->getKey();
        $programId = (string) $level?->program_id;

        return [
            'code' => [
                'sometimes',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                'unique:levels,code,'.$levelId.',id,program_id,'.$programId,
            ],
            'name' => ['sometimes', 'array', 'min:1'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:'.(int) config('academics.reason.minimum_length'), 'max:'.(int) config('academics.reason.maximum_length')],
        ];
    }
}

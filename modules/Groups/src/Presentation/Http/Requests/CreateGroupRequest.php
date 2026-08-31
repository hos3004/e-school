<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Groups\Domain\Models\Group;

/**
 * طلب إنشاء مجموعة جديدة.
 */
final class CreateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Group::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['prohibited'],
            'code' => [
                'required',
                'string',
                'max:32',
                'unique:groups,code',
                'regex:/^[A-Za-z0-9-]+$/',
            ],
            'name' => ['required', 'array'],
            'name.ar' => ['required_with:name', 'string', 'max:120'],
            'name.en' => ['nullable', 'string', 'max:120'],
            'name.fr' => ['nullable', 'string', 'max:120'],
            'capacity' => [
                'required',
                'integer',
                'min:'.(int) config('groups.capacity.minimum'),
                'max:'.(int) config('groups.capacity.maximum'),
            ],
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => __('groups::validation.code_taken'),
            'capacity.max' => __('groups::validation.capacity_too_large'),
            'ends_on.after_or_equal' => __('groups::validation.ends_before_starts'),
            'reason.required' => __('groups::validation.reason_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect([
            'code',
            'name',
            'capacity',
            'timezone',
            'starts_on',
            'ends_on',
            'reason',
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('groups::attributes.'.$field)],
        )->all();
    }
}

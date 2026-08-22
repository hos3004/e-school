<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Groups\Domain\Models\Group;

/**
 * طلب تعديل بيانات مجموعة قائمة.
 * الحالة والمعرّف التنظيمي لا يُقبلان هنا أصلًا — تغيير الحالة عبر مساراتها الخاصة.
 */
final class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('group'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Group|null $group */
        $group = $this->route('group');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:32',
                'unique:groups,code'.($group !== null ? ','.$group->getKey() : ''),
                'regex:/^[A-Za-z0-9-]+$/',
            ],
            'name' => ['sometimes', 'array'],
            'name.ar' => ['required_with:name', 'string', 'max:120'],
            'name.en' => ['nullable', 'string', 'max:120'],
            'name.fr' => ['nullable', 'string', 'max:120'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'timezone' => ['sometimes', 'string', 'max:64', 'timezone:all'],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['nullable', 'date'],
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
        ])->mapWithKeys(
            fn (string $field): array => [$field => __('groups::attributes.'.$field)],
        )->all();
    }
}

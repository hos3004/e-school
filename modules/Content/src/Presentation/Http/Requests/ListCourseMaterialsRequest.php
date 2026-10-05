<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Content\Domain\Enums\MaterialType;

/**
 * طلب سرد المواد التعليمية مع مُصفّيات اختيارية.
 */
final class ListCourseMaterialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('content.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'string', 'size:26'],
            'type' => ['nullable', 'string', Rule::enum(MaterialType::class)],
            'only_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;

/**
 * طلب تعديل مادة تعليمية قائمة.
 */
final class UpdateCourseMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $material = $this->route('material');

        if ($material instanceof CourseMaterial) {
            return $this->user()->can('update', $material);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSizeMb = (int) config('content.uploads.max_size_mb', 100);

        return [
            'title' => ['sometimes', 'array'],
            'title.ar' => ['required_with:title', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.fr' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'description.fr' => ['nullable', 'string'],
            'type' => ['sometimes', 'string', Rule::enum(MaterialType::class)],
            'disk' => ['nullable', 'string', 'max:64'],
            'path' => ['nullable', 'string', 'max:1024'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'size_bytes' => ['nullable', 'integer', 'min:0', 'max:'.($maxSizeMb * 1024 * 1024)],
            'visible_from' => ['nullable', 'date'],
            'visible_to' => ['nullable', 'date'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:'.config('content.reason_max_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'size_bytes.max' => __('content::errors.file_too_large', ['max_mb' => config('content.uploads.max_size_mb')]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('content::fields.title'),
            'type' => __('content::fields.type'),
            'disk' => __('content::fields.disk'),
            'path' => __('content::fields.path'),
            'external_url' => __('content::fields.external_url'),
            'size_bytes' => __('content::fields.size_bytes'),
            'visible_from' => __('content::fields.visible_from'),
            'visible_to' => __('content::fields.visible_to'),
        ];
    }
}

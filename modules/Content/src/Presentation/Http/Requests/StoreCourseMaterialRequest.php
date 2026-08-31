<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Content\Domain\Enums\MaterialType;

/**
 * طلب رفع مادة تعليمية جديدة.
 */
final class StoreCourseMaterialRequest extends FormRequest
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
        $maxSizeMb = (int) config('content.uploads.max_size_mb', 100);
        /** @var list<string> $allowed */
        $allowed = config('content.uploads.allowed_extensions', []);

        return [
            'course_id' => ['required', 'string', 'size:26'],
            'title' => ['required', 'array'],
            'title.ar' => ['required_with:title', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'title.fr' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'description.fr' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::enum(MaterialType::class)],
            'disk' => ['required_if:type,'.MaterialType::File->value, 'nullable', 'string', 'max:64'],
            'path' => [
                'required_if:type,'.MaterialType::File->value,
                'nullable',
                'string',
                'max:1024',
                Rule::when(
                    fn (): bool => $this->string('type')->toString() === MaterialType::File->value,
                    [function (string $attribute, mixed $value, \Closure $fail) use ($allowed): void {
                        $extension = strtolower(pathinfo((string) $value, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowed, true)) {
                            $fail(__('content::errors.extension_not_allowed', ['extension' => $extension]));
                        }
                    }],
                ),
            ],
            'external_url' => ['required_if:type,'.MaterialType::Link->value, 'nullable', 'url', 'max:2048'],
            'size_bytes' => ['nullable', 'integer', 'min:0', 'max:'.($maxSizeMb * 1024 * 1024)],
            'visible_from' => ['nullable', 'date'],
            'visible_to' => ['nullable', 'date', 'after_or_equal:visible_from'],
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
            'disk.required_if' => __('content::errors.file_requires_storage'),
            'path.required_if' => __('content::errors.file_requires_storage'),
            'external_url.required_if' => __('content::errors.link_requires_url'),
            'size_bytes.max' => __('content::errors.file_too_large', ['max_mb' => config('content.uploads.max_size_mb')]),
            'visible_to.after_or_equal' => __('content::errors.visibility_window_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'course_id' => __('content::fields.course'),
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

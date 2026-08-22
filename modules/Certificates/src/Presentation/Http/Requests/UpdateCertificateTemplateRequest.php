<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تعديل قالب شهادة قائم.
 */
final class UpdateCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certificates.template.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'program_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'name' => ['sometimes', 'array'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'layout' => ['sometimes', 'array'],
            'background_image_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'program_id' => __('certificates::fields.program'),
            'name' => __('certificates::fields.name'),
            'layout' => __('certificates::fields.layout'),
            'background_image_path' => __('certificates::fields.background_image'),
            'is_active' => __('certificates::fields.is_active'),
        ];
    }
}

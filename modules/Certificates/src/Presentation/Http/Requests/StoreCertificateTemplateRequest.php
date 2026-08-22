<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إنشاء قالب شهادة.
 */
final class StoreCertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certificates.template.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'program_id' => ['nullable', 'string', 'size:26'],
            'name' => ['required', 'array'],
            'name.ar' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'layout' => ['required', 'array'],
            'background_image_path' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('certificates::fields.organization'),
            'program_id' => __('certificates::fields.program'),
            'name' => __('certificates::fields.name'),
            'layout' => __('certificates::fields.layout'),
            'background_image_path' => __('certificates::fields.background_image'),
            'is_active' => __('certificates::fields.is_active'),
        ];
    }
}

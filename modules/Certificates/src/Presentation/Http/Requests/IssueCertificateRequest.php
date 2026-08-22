<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إصدار شهادة لطالب.
 */
final class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certificates.certificate.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'certificate_template_id' => ['nullable', 'string', 'size:26'],
            'student_profile_id' => ['required', 'string', 'size:26'],
            'program_id' => ['nullable', 'string', 'size:26'],
            'enrollment_id' => ['nullable', 'string', 'size:26'],
            'title' => ['required', 'array'],
            'title.ar' => ['required_with:title', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['sometimes', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:issued_at'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certificate_template_id' => __('certificates::fields.template'),
            'student_profile_id' => __('certificates::fields.student'),
            'program_id' => __('certificates::fields.program'),
            'enrollment_id' => __('certificates::fields.enrollment'),
            'title' => __('certificates::fields.title'),
            'issued_at' => __('certificates::fields.issued_at'),
            'expires_at' => __('certificates::fields.expires_at'),
            'metadata' => __('certificates::fields.metadata'),
        ];
    }
}

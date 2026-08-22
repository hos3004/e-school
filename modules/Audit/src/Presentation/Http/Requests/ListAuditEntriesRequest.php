<?php

declare(strict_types=1);

namespace Modules\Audit\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب تصفّح قيود التدقيق — قراءة فقط.
 */
final class ListAuditEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('audit.view_any');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:128'],
            'auditable_type' => ['nullable', 'string', 'max:191'],
            'auditable_id' => ['nullable', 'string', 'size:26'],
            'actor_id' => ['nullable', 'string', 'size:26'],
            'correlation_id' => ['nullable', 'string', 'size:26'],
            'organization_id' => ['nullable', 'string', 'size:26'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.size' => __('audit::validation.must_be_ulid'),
            'per_page.max' => __('audit::validation.per_page_too_large'),
        ];
    }
}

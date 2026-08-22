<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Content\Domain\Models\CourseMaterial;

/**
 * طلب إزالة مادة تعليمية — السبب إلزامي للتدقيق.
 */
final class DeleteCourseMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $material = $this->route('material');

        if ($material instanceof CourseMaterial) {
            return $this->user()->can('delete', $material);
        }

        return $this->user()->can('content.material.delete');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('content::errors.removal_reason_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('content::fields.reason'),
        ];
    }
}

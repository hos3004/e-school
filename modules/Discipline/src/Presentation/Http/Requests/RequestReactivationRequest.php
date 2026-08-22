<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Discipline\Domain\Models\ReactivationRequest;

/**
 * طلب تقديم إعادة تفعيل — إفادة الطالب إلزامية (بيان الجدية).
 */
final class RequestReactivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ReactivationRequest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', 'size:26'],
            'enrollment_id' => ['required', 'string', 'size:26'],
            'student_statement' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('discipline::attributes.organization_id'),
            'enrollment_id' => __('discipline::attributes.enrollment_id'),
            'student_statement' => __('discipline::attributes.student_statement'),
        ];
    }
}

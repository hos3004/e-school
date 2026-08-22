<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Assignments\Domain\Models\AssignmentSubmission;

/**
 * طلب تسليم النشاط — المحتوى أو المرفقات على الأقل.
 */
final class SubmitAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AssignmentSubmission $submission */
        $submission = $this->route('submission');

        return $this->user()->can('create', AssignmentSubmission::class)
            && $this->user()->can('view', $submission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['string', 'max:2048'],
        ];
    }

    /**
     * @param mixed $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (!$this->has('content') && !$this->has('attachments')) {
                $validator->errors()->add(
                    'content',
                    __('assignments::validation.submission_empty'),
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'content' => __('assignments::attributes.content'),
            'attachments' => __('assignments::attributes.attachments'),
        ];
    }
}

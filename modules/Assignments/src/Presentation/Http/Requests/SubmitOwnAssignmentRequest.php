<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Assignments\Domain\Models\Assignment;

final class SubmitOwnAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        return $assignment instanceof Assignment
            && $this->user()?->can('assignment.submit') === true
            && $this->user()->can('view', $assignment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*' => ['string', 'max:2048'],
        ];
    }

    public function withValidator(mixed $validator): void
    {
        $validator->after(function ($validator): void {
            if (!$this->filled('content') && $this->array('attachments') === []) {
                $validator->errors()->add('content', __('assignments::validation.submission_empty'));
            }
        });
    }
}

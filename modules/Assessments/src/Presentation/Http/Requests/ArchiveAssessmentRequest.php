<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ArchiveAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assessment.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:'.(int) config('assessments.reason_max_length', 1000)],
        ];
    }
}

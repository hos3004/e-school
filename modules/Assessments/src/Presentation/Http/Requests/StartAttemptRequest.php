<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assessment.take') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reactivation_request_id' => ['nullable', 'string', 'size:26'],
        ];
    }
}

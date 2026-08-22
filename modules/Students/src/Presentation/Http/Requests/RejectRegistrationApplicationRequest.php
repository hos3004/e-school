<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Students\Domain\Models\RegistrationApplication;

final class RejectRegistrationApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('registrationApplication');

        return $application instanceof RegistrationApplication
            && (bool) $this->user()?->can('reject', $application);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => [
                Rule::requiredIf((bool) config('admission.application.rejection_requires_reason', true)),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => __('students::attributes.decision_reason')];
    }
}

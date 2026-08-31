<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Students\Domain\Models\RegistrationApplication;

final class AcceptRegistrationApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('registrationApplication');

        return $application instanceof RegistrationApplication
            && (bool) $this->user()?->can('accept', $application);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => [
                (bool) config('admission.application.acceptance_requires_reason', true) ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}

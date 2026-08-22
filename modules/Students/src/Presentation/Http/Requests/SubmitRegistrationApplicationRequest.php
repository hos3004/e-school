<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Students\Domain\Models\RegistrationApplication;

final class SubmitRegistrationApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('registrationApplication');

        return $application instanceof RegistrationApplication
            && (bool) $this->user()?->can('submit', $application);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Models\GuardianProfile;

final class UpdateGuardianProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var GuardianProfile|null $profile */
        $profile = $this->route('guardian_profile');

        return $profile !== null && $this->user()->can('update', $profile);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'national_id_last4' => ['sometimes', 'nullable', 'digits:4'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_contact_channel' => ['sometimes', 'nullable', Rule::enum(ContactChannel::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'national_id_last4.digits' => __('guardians::validation.national_id_last4_digits'),
            'occupation.max' => __('guardians::validation.occupation_max'),
            'preferred_contact_channel.Illuminate\Validation\Rules\Enum' => __('guardians::validation.contact_channel_invalid'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;

final class StoreGuardianProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organizationId = data_get($user, 'organization_id');
        $requestedOrganizationId = $this->input('organization_id');
        $userId = $this->input('user_id');

        if ($user === null || !$user->can('guardian.link') || !is_string($organizationId)) {
            return false;
        }

        if (!is_string($requestedOrganizationId) || !Str::isUlid($requestedOrganizationId)
            || !is_string($userId) || !Str::isUlid($userId)) {
            return true;
        }

        if (!hash_equals($organizationId, $requestedOrganizationId)) {
            return false;
        }

        return app(UserAccountDirectory::class)->find($organizationId, $userId) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'ulid'],
            'user_id' => ['required', 'ulid'],
            'national_id_last4' => ['nullable', 'digits:4'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'preferred_contact_channel' => ['nullable', Rule::enum(ContactChannel::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organization_id.required' => __('guardians::validation.organization_id_required'),
            'organization_id.size' => __('guardians::validation.identifier_size'),
            'user_id.required' => __('guardians::validation.user_id_required'),
            'user_id.size' => __('guardians::validation.identifier_size'),
            'national_id_last4.digits' => __('guardians::validation.national_id_last4_digits'),
            'occupation.max' => __('guardians::validation.occupation_max'),
            'preferred_contact_channel.Illuminate\Validation\Rules\Enum' => __('guardians::validation.contact_channel_invalid'),
        ];
    }
}

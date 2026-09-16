<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Reporting\Domain\Enums\DigestRecipientType;

final class SaveProgramDigestRecipientSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('reporting.settings.manage')
            && is_string(data_get($this->user(), 'organization_id'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recipient_type' => ['required', 'string', 'in:'.implode(',', array_map(
                static fn (DigestRecipientType $type): string => $type->value,
                DigestRecipientType::cases(),
            ))],
            'recipient_user_id' => ['nullable', 'string', 'required_if:recipient_type,'.DigestRecipientType::StaffEmail->value],
            'custom_email' => ['nullable', 'email', 'max:255', 'required_if:recipient_type,'.DigestRecipientType::CustomEmail->value],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'version' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'recipient_type' => __('reporting::settings.digest_recipient.fields.type'),
            'recipient_user_id' => __('reporting::settings.digest_recipient.fields.user'),
            'custom_email' => __('reporting::settings.digest_recipient.fields.email'),
            'reason' => __('reporting::settings.digest_recipient.fields.reason'),
        ];
    }
}

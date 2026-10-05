<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterGreenApiWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()->can('settings.manage')
            && (bool) $this->user()->can('integrations.connection.update')
            && is_string(data_get($this->user(), 'organization_id'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}

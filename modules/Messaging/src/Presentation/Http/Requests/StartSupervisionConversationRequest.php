<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartSupervisionConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('message.send') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:'.(int) config('messaging.limits.message_body_max'),
            ],
        ];
    }
}

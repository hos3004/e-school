<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartDirectConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('message.send') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recipient_user_id' => ['required', 'string', 'size:26'],
            'subject' => [
                'required',
                'string',
                'max:'.(int) config('messaging.limits.conversation_subject_max'),
            ],
            'body' => [
                'required',
                'string',
                'max:'.(int) config('messaging.limits.message_body_max'),
            ],
        ];
    }
}

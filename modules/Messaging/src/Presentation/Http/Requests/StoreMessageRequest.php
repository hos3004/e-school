<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Messaging\Domain\Models\Conversation;

/**
 * طلب إرسال رسالة داخل محادثة.
 */
final class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('messaging.message.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:'.(int) config('messaging.limits.message_body_max'),
            ],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['array'],
        ];
    }

    public function conversation(): Conversation
    {
        /** @var Conversation $conversation */
        $conversation = $this->route('conversation');

        return $conversation;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('messaging::validation.body_required'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Messaging\Domain\Models\Message;

/**
 * طلب تعديل رسالة.
 */
final class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('messaging.message.update');
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
        ];
    }

    public function message(): Message
    {
        /** @var Message $message */
        $message = $this->route('message');

        return $message;
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

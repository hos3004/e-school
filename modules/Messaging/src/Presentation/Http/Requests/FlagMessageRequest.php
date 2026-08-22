<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Messaging\Domain\Models\Message;

/**
 * طلب وسم رسالة كمخالفة.
 */
final class FlagMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('messaging.message.flag');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
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
            'reason.required' => __('messaging::validation.reason_required'),
        ];
    }
}

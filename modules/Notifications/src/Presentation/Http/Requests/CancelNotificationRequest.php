<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إلغاء رسالة في الانتظار.
 */
final class CancelNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('notifications.outbox.cancel') ?? false);
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('notifications::fields.reason'),
        ];
    }
}

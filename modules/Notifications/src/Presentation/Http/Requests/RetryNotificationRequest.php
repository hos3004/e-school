<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إعادة محاولة رسالة فاشلة بسبب تشغيلي موثّق.
 */
final class RetryNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('settings.manage') ?? false);
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

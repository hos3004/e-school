<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Domain\Enums\Channel;

/**
 * طلب قيد رسالة يدويًا في صندوق الإرسال.
 */
final class QueueNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('notifications.outbox.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // المؤسسة تُشتق من المستخدم المصادق عليه ولا تُقبل من العميل.
            'organization_id' => ['prohibited'],
            'user_id' => ['required', 'string', 'size:26'],
            'category' => ['required', 'string', 'max:64'],
            'channel' => ['required', 'string', Rule::in(Channel::values())],
            'event_name' => ['required', 'string', 'max:128'],
            'event_id' => ['required', 'string', 'size:26'],
            'locale' => ['required', 'string', 'max:16'],
            'scheduled_for' => ['nullable', 'date', 'after_or_equal:now'],
            'subject' => ['required', 'array'],
            'subject.*' => ['required', 'string', 'max:255'],
            'body' => ['required', 'array'],
            'body.*' => ['required', 'string'],
            'payload' => ['nullable', 'array'],
            'correlation_id' => ['nullable', 'string', 'max:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_id' => __('notifications::fields.organization_id'),
            'user_id' => __('notifications::fields.user_id'),
            'category' => __('notifications::fields.category'),
            'channel' => __('notifications::fields.channel'),
            'event_name' => __('notifications::fields.event_name'),
            'event_id' => __('notifications::fields.event_id'),
            'locale' => __('notifications::fields.locale'),
            'scheduled_for' => __('notifications::fields.scheduled_for'),
            'subject' => __('notifications::fields.subject'),
            'body' => __('notifications::fields.body'),
            'payload' => __('notifications::fields.payload'),
            'correlation_id' => __('notifications::fields.correlation_id'),
        ];
    }
}

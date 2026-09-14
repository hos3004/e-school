<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\ManualAudience;
use Modules\Notifications\Domain\Enums\ManualRecipientType;

/**
 * رسالة جماعية لأطراف فصل: مجموعة أو كورس أو جدول.
 *
 * الإرسال رسائل فردية منفصلة لكل مستلم — لا مجموعة ولا قائمة بث — فلا يرى
 * مستلم رقم مستلم آخر. الجمهور يحدد مَن من أطراف الفصل يُراسَل.
 */
final class SendAudienceMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.outbox.create') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_type' => [
                'required',
                Rule::in([
                    ManualRecipientType::Group->value,
                    ManualRecipientType::Course->value,
                    ManualRecipientType::Schedule->value,
                ]),
            ],
            'target_id' => ['required', 'string', 'size:26'],
            'audience' => ['required', Rule::in(array_keys(ManualAudience::options()))],
            'channel' => ['required', Rule::in(Channel::values())],
            'template_id' => ['nullable', 'string', 'size:26'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'request_id' => ['required', 'string', 'size:26'],
            // المستقبلية تُفحص في المتحكّم بتوقيت المُرسِل: حقل datetime-local
            // بلا منطقة زمنية، وفحصه هنا يقيسه بتوقيت التطبيق فيرفض موعدًا صحيحًا.
            'scheduled_for' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recipient_type' => __('console_messaging.fields.recipient_type'),
            'target_id' => __('console_messaging.fields.target'),
            'audience' => __('console_messaging.fields.audience'),
            'channel' => __('console_messaging.fields.channel'),
            'subject' => __('console_messaging.fields.subject'),
            'body' => __('console_messaging.fields.body'),
            'reason' => __('console_messaging.fields.reason'),
            'scheduled_for' => __('console_messaging.fields.scheduled_for'),
        ];
    }
}

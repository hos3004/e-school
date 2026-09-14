<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use App\Http\Controllers\Console\Support\ConsoleMessageComposer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Domain\Enums\Channel;

/**
 * رسالة يدوية من الملف الشخصي لمستخدم واحد.
 *
 * الحقول المسموح إرسالها في رسالة بيانات الحساب قائمة مغلقة، والسبب مطلوب
 * دائمًا لأن الإرسال فعل إداري يُسجَّل في التدقيق — وكلمة المرور المؤقتة
 * تبطل كلمة المرور الحالية فلا تُرسل بلا سبب مكتوب.
 */
final class SendPersonMessageRequest extends FormRequest
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
            'kind' => ['required', Rule::in(['credentials', 'schedule', 'free_text'])],
            'channel' => ['required', Rule::in(Channel::values())],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'request_id' => ['required', 'string', 'size:26'],
            // المستقبلية تُفحص في المتحكّم بتوقيت المُرسِل: حقل datetime-local
            // بلا منطقة زمنية، وفحصه هنا يقيسه بتوقيت التطبيق فيرفض موعدًا صحيحًا.
            'scheduled_for' => ['nullable', 'date'],

            'fields' => ['required_if:kind,credentials', 'array', 'min:1'],
            'fields.*' => [Rule::in(ConsoleMessageComposer::CREDENTIAL_FIELDS)],

            'subject' => ['required_if:kind,free_text', 'nullable', 'string', 'max:255'],
            'body' => ['required_if:kind,free_text', 'nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => __('console_messaging.fields.kind'),
            'channel' => __('console_messaging.fields.channel'),
            'reason' => __('console_messaging.fields.reason'),
            'scheduled_for' => __('console_messaging.fields.scheduled_for'),
            'fields' => __('console_messaging.fields.fields'),
            'subject' => __('console_messaging.fields.subject'),
            'body' => __('console_messaging.fields.body'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Domain\Enums\Channel;

/**
 * إنشاء قالب رسالة أو تعديله من الكونسول.
 *
 * قائمة المتغيرات لا تُقبل من العميل: تُستخرج من نص القالب نفسه على الخادم،
 * وإلا أمكن حفظ قالب يعلن متغيرًا لا يذكره فيسقط عند الإرسال، أو يذكر متغيرًا
 * لا يعلنه فيصل للمستلم بأقواسه كما هي.
 */
final class NotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parameters' => ['prohibited'],
            'organization_id' => ['prohibited'],
            'event_key' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9]+(?:[._][a-z0-9]+)*$/'],
            'channel' => ['required', Rule::in(Channel::values())],
            'locale' => ['required', 'string', Rule::in($this->supportedLocales())],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
            'provider_template_name' => ['nullable', 'string', 'max:128', 'regex:/^[a-z0-9_]+$/'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event_key' => __('console_messaging.template_fields.event_key'),
            'channel' => __('console_messaging.fields.channel'),
            'locale' => __('console_messaging.template_fields.locale'),
            'subject' => __('console_messaging.fields.subject'),
            'body' => __('console_messaging.fields.body'),
            'provider_template_name' => __('console_messaging.template_fields.provider_template_name'),
            'is_active' => __('console_messaging.template_fields.is_active'),
        ];
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        $locales = (array) config('notifications.localization.supported', ['ar', 'en']);

        return array_values(array_filter(
            $locales,
            static fn (mixed $locale): bool => is_string($locale) && $locale !== '',
        ));
    }
}

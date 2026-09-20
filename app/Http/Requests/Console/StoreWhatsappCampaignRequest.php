<?php

declare(strict_types=1);

namespace App\Http\Requests\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * إنشاء حملة واتساب إلى قائمة أرقام.
 *
 * القائمة تصل إما ملفًا مرفوعًا أو نصًا ملصوقًا أو كليهما معًا، فالمرسِل قد
 * يرفع ملف الدورة ثم يضيف رقمين نسيهما. أحدهما على الأقل مطلوب.
 */
final class StoreWhatsappCampaignRequest extends FormRequest
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
        $delay = (array) config('messaging.campaigns.delay', []);
        $media = (array) config('messaging.campaigns.media', []);
        $floor = (int) ($delay['floor_seconds'] ?? 3);
        $ceiling = (int) ($delay['ceiling_seconds'] ?? 3600);

        return [
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],

            'recipients_text' => ['nullable', 'string', 'max:500000'],
            'recipients_file' => ['nullable', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls,ods'],

            'delay_min_seconds' => ['required', 'integer', 'min:'.$floor, 'max:'.$ceiling],
            'delay_max_seconds' => [
                'required',
                'integer',
                'min:'.$floor,
                'max:'.$ceiling,
                'gte:delay_min_seconds',
            ],

            'media' => ['nullable', 'array', 'max:'.(int) ($media['max_files'] ?? 5)],
            /*
             * الأنواع المسموحة تُقرأ من الإعداد لا من قائمة مكتوبة هنا، فإضافة
             * نوع جديد تغييرُ إعدادٍ واحد لا تعديلُ كود.
             */
            'media.*' => [
                'file',
                'max:'.(int) ($media['max_size_kilobytes'] ?? 16384),
                'mimetypes:'.implode(',', (array) ($media['allowed_mime_types'] ?? [])),
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasText = trim((string) $this->input('recipients_text', '')) !== '';

                if (!$hasText && !$this->hasFile('recipients_file')) {
                    $validator->errors()->add(
                        'recipients_text',
                        (string) __('console_whatsapp.campaigns.errors.recipients_required'),
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => (string) __('console_whatsapp.campaigns.fields.name'),
            'body' => (string) __('console_whatsapp.campaigns.fields.body'),
            'reason' => (string) __('console_whatsapp.campaigns.fields.reason'),
            'recipients_text' => (string) __('console_whatsapp.campaigns.fields.recipients_text'),
            'recipients_file' => (string) __('console_whatsapp.campaigns.fields.recipients_file'),
            'delay_min_seconds' => (string) __('console_whatsapp.campaigns.fields.delay_min'),
            'delay_max_seconds' => (string) __('console_whatsapp.campaigns.fields.delay_max'),
            'media' => (string) __('console_whatsapp.campaigns.fields.media'),
        ];
    }
}

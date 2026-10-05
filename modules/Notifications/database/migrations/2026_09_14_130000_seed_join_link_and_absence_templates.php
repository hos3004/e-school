<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/*
 * تركيب القوالب العامة للأحداث الجديدة.
 *
 * TemplateRenderer يقرأ من جدول notification_templates وحده؛ ملف اللغة مصدر
 * للـseeder لا مصدر تشغيل. وتشغيل seeders على قاعدة الموقع ممنوع. فلولا هذه
 * الهجرة لسقط كل تذكير برابط دخول وكل إخطار غياب بـtemplate_missing بعد النشر.
 *
 * idempotent: تتخطى أي صف موجود، فلا تدهس تخصيصًا سابقًا ولا تكرر الإدراج.
 * القوالب المُدرجة عامة (organization_id = null)، وتخصيص المؤسسة يبقى نسخة
 * خاصة تتفوق عليها عند العرض.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const EVENT_KEYS = [
        'session.join_link.teacher',
        'session.join_link.student',
        'discipline.absence_recorded',
    ];

    /** @var list<string> */
    private const CHANNELS = ['in_app', 'email', 'whatsapp'];

    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    public function up(): void
    {
        $now = now();

        foreach (self::LOCALES as $locale) {
            /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
            $templates = Lang::get('notifications::templates', [], $locale);

            foreach (self::EVENT_KEYS as $eventKey) {
                $template = $templates[$eventKey] ?? null;

                if (!is_array($template)) {
                    continue;
                }

                foreach (self::CHANNELS as $channel) {
                    $exists = DB::table('notification_templates')
                        ->whereNull('organization_id')
                        ->where('event_key', $eventKey)
                        ->where('channel', $channel)
                        ->where('locale', $locale)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('notification_templates')->insert([
                        'id' => (string) Str::ulid(),
                        'organization_id' => null,
                        'event_key' => $eventKey,
                        'channel' => $channel,
                        'locale' => $locale,
                        'subject' => $template['subject'],
                        'body' => $template['body'],
                        'provider_template_name' => $channel === 'whatsapp'
                            ? str_replace('.', '_', $eventKey)
                            : null,
                        'parameters' => json_encode(
                            array_values($template['parameters'] ?? []),
                            JSON_UNESCAPED_UNICODE,
                        ),
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // القوالب العامة فقط؛ أي نسخة خاصة بمؤسسة تبقى كما هي.
        DB::table('notification_templates')
            ->whereNull('organization_id')
            ->whereIn('event_key', self::EVENT_KEYS)
            ->delete();
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/*
 * تركيب القوالب الناقصة لكل حدث مُعدّ في الإشعارات.
 *
 * TemplateRenderer يقرأ من notification_templates وحده؛ ملف اللغة مصدر
 * للـseeder لا مصدر تشغيل، وتشغيل seeders على قاعدة الموقع ممنوع. فحدثٌ
 * معرّف في config وله نص في ملف اللغة وليس له صف في الجدول يخرج بمتن فارغ:
 * يسقط في واتساب بـwhatsapp_body_empty، ويصل في البريد وداخل المنصة بلا نص.
 *
 * حدث هذا فعلًا مع postponement.requested وpostponement.scheduled: 21 إشعارًا
 * من كل منهما خرجت بلا متن قبل 17 سبتمبر 2026. هذه الهجرة تسدّ الصنف كله لا
 * الحالتين وحدهما.
 *
 * idempotent: تتخطى أي صف موجود، فلا تدهس تخصيصًا سابقًا ولا تكرر الإدراج.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CHANNELS = ['in_app', 'email', 'whatsapp'];

    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    public function up(): void
    {
        $now = now();
        $eventKeys = array_keys((array) config('notifications.events', []));

        foreach (self::LOCALES as $locale) {
            /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
            $templates = Lang::get('notifications::templates', [], $locale);

            foreach ($eventKeys as $eventKey) {
                if (!is_string($eventKey)) {
                    continue;
                }

                $template = $templates[$eventKey] ?? null;

                // حدث بلا نص في ملف اللغة لا يُخترع له نص هنا.
                if (!is_array($template)
                    || !is_string($template['subject'] ?? null)
                    || !is_string($template['body'] ?? null)) {
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
        /*
         * لا حذف: الهجرة تسدّ نقصًا ولا تملك ما أدرجته وحدها — صفوف أخرى لنفس
         * المفاتيح ركّبتها هجرات سابقة، وحذفها هنا يعيد الصمت الذي جاءت لتنهيه.
         */
    }
};

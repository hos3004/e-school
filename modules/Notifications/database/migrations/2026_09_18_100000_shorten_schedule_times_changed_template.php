<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/*
 * تقصير قالب «تم تعديل مواعيد الحصص»: كان يسرد كل موعد مولّد من الجدول —
 * حتى 77 سطرًا لتعديل واحد — فوصلت الرسالة على واتساب في ثلاث فقاعات متتالية
 * لا تُقرأ. صار النص يذكر النمط الأسبوعي فقط: «الثلاثاء 17:00 والجمعة 17:30».
 *
 * القالب العام مرجع مشترك بين المؤسسات؛ هذه الهجرة تُحدّث النسخة العامة فقط
 * ولا تمس أي تخصيص أنشأته مؤسسة. القوالب المُركَّبة سابقًا (2026-09-17) لن
 * تلتقط تعديل ملف اللغة تلقائيًا لأن الجدول هو مصدر العرض، لا الملف.
 */
return new class extends Migration
{
    private const EVENT_KEY = 'schedule.times_changed';

    /** @var list<string> */
    private const CHANNELS = ['in_app', 'email', 'whatsapp'];

    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    public function up(): void
    {
        foreach (self::LOCALES as $locale) {
            /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
            $templates = Lang::get('notifications::templates', [], $locale);
            $template = $templates[self::EVENT_KEY] ?? null;

            if (!is_array($template)) {
                continue;
            }

            foreach (self::CHANNELS as $channel) {
                DB::table('notification_templates')
                    ->whereNull('organization_id')
                    ->where('event_key', self::EVENT_KEY)
                    ->where('channel', $channel)
                    ->where('locale', $locale)
                    ->update([
                        'subject' => $template['subject'],
                        'body' => $template['body'],
                        'parameters' => json_encode(
                            array_values($template['parameters'] ?? []),
                            JSON_UNESCAPED_UNICODE,
                        ),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // لا تراجع: النص القديم نفسه كان الخلل الذي جاءت هذه الهجرة لتصلحه.
    }
};

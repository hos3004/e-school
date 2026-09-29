<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/*
 * تقصير قالب «تم اعتماد الجدول الدراسي»: كان يسرد كل موعد مولّد من الجدول —
 * 17 سطرًا بصيغة «2026-09-30 20:00 EEST» — فوصلت رسالة واتساب الواحدة كجدار
 * أرقام تنقلب فيه الأرقام مع اتجاه العربية. صار النص يذكر النمط الأسبوعي
 * فقط، كما فعلنا في «تعديل المواعيد» (2026-09-18).
 *
 * الرسالة تُعرض من جدول notification_templates لا من ملف اللغة، لذلك هذه
 * الهجرة هي التي تغيّر ما يصل فعلًا. القالب العام يُحدَّث كله؛ ونسخة المؤسسة
 * تُحدَّث فقط إن كانت مطابقة حرفيًا للنص العام القديم (نسخة لم يعدّلها أحد
 * ليست تخصيصًا)، وأي نص كتبته مؤسسة فعلًا لا تمسّه.
 */
return new class extends Migration
{
    private const EVENT_KEY = 'schedule.created';

    /** @var list<string> */
    private const CHANNELS = ['in_app', 'email', 'whatsapp'];

    /** @var array<string, string> */
    private const PREVIOUS_BODIES = [
        'ar' => 'تم اعتماد جدول {{target_name}} في كورس {{course_name}} ({{course_code}}) مع المعلم {{teacher_name}}. مدة الحصة {{duration_minutes}} دقيقة، وعدد الحصص {{session_count}}. مواعيد الحصص: {{schedule_times}}',
        'en' => 'The schedule for {{target_name}} in {{course_name}} ({{course_code}}) with {{teacher_name}} has been confirmed. Session duration: {{duration_minutes}} minutes. Total sessions: {{session_count}}. Session times: {{schedule_times}}',
    ];

    public function up(): void
    {
        foreach (self::PREVIOUS_BODIES as $locale => $previousBody) {
            /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
            $templates = Lang::get('notifications::templates', [], $locale);
            $template = $templates[self::EVENT_KEY] ?? null;

            if (!is_array($template)
                || !is_string($template['subject'] ?? null)
                || !is_string($template['body'] ?? null)) {
                continue;
            }

            $values = [
                'subject' => $template['subject'],
                'body' => $template['body'],
                'parameters' => json_encode(
                    array_values($template['parameters'] ?? []),
                    JSON_UNESCAPED_UNICODE,
                ),
                'updated_at' => now(),
            ];

            DB::table('notification_templates')
                ->whereNull('organization_id')
                ->where('event_key', self::EVENT_KEY)
                ->whereIn('channel', self::CHANNELS)
                ->where('locale', $locale)
                ->update($values);

            DB::table('notification_templates')
                ->whereNotNull('organization_id')
                ->where('event_key', self::EVENT_KEY)
                ->where('locale', $locale)
                ->where('body', $previousBody)
                ->update($values);
        }
    }

    public function down(): void
    {
        // لا تراجع: النص القديم نفسه كان الخلل الذي جاءت هذه الهجرة لتصلحه.
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/*
 * إغناء قوالب التسجيل الثلاثة: التقديم والقبول والرفض.
 *
 * كانت الرسائل بلا أي بارامتر — «تم اعتماد طلب التسجيل بنجاح. يمكنك الآن
 * متابعة خطوات البدء.» — فلا تذكر الطالب ولا الكورس ولا الخطوة التالية.
 * والطالب الذي يقدّم طلبين لكورسين مختلفين كان يصله نصّان متطابقان حرفًا
 * بحرف، فلا يعرف أيهما اعتُمد. صار النص يحمل اسم الطالب والكورس، ومع القبول
 * كود الطالب، ومع الرفض السبب المكتوب، ومع التقديم تاريخه.
 *
 * TemplateRenderer يقرأ من notification_templates وحده؛ ملف اللغة مصدر
 * للـseeder لا مصدر عرض، وتشغيل seeders على قاعدة الموقع ممنوع — فبدون هذه
 * الهجرة يبقى النص القديم معروضًا بعد نشر الكود.
 *
 * تُحدَّث النسخة العامة فقط (organization_id IS NULL)؛ أي تخصيص أنشأته مؤسسة
 * يبقى كما هو ويحتاج تحديثه من لوحة القوالب.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const EVENT_KEYS = [
        'registration.submitted',
        'registration.approved',
        'registration.rejected',
    ];

    /** @var list<string> */
    private const CHANNELS = ['in_app', 'email', 'whatsapp'];

    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    public function up(): void
    {
        foreach (self::LOCALES as $locale) {
            /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
            $templates = Lang::get('notifications::templates', [], $locale);

            foreach (self::EVENT_KEYS as $eventKey) {
                $template = $templates[$eventKey] ?? null;

                if (!is_array($template)
                    || !is_string($template['subject'] ?? null)
                    || !is_string($template['body'] ?? null)) {
                    continue;
                }

                DB::table('notification_templates')
                    ->whereNull('organization_id')
                    ->where('event_key', $eventKey)
                    ->whereIn('channel', self::CHANNELS)
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
        /*
         * لا تراجع: النص القديم نفسه كان الخلل. والرجوع إليه مع كود ينشر
         * بارامترات جديدة لا يعيد حالة سابقة متسقة، بل يعيد رسالة بلا معنى.
         */
    }
};

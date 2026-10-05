<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/*
 * تحديث نسخ المؤسسة المطابقة حرفيًا للنص العام القديم لقوالب التسجيل.
 *
 * TemplateRenderer يفضّل صف المؤسسة على الصف العام. وفي 16 سبتمبر 2026
 * أُنشئت من شاشة قوالب واتساب في اللوحة نسخة مؤسسة لكل حدث على قناة واتساب
 * بالعربية — نسخًا حرفيًا من النص العام بلا أي تعديل. لذلك بقيت رسالة واتساب
 * العربية — وهي القناة التي اشتكى منها المستخدم — تعرض النص القديم بعد
 * تحديث القالب العام في الهجرة السابقة.
 *
 * الشرط هنا هو المساواة الحرفية مع النص العام القديم: نسخة نسخها أحد ولم
 * يعدّلها ليست تخصيصًا، وتحديثها يعيدها إلى مرجعها. وأي نص كتبته المؤسسة
 * فعلًا لا يطابق الشرط فلا تمسّه هذه الهجرة.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private const PREVIOUS_BODIES = [
        'registration.submitted' => [
            'ar' => 'تم استلام طلب التسجيل، وسيصلك إشعار عند اكتمال المراجعة.',
            'en' => 'Your registration request was received. You will be notified when the review is complete.',
        ],
        'registration.approved' => [
            'ar' => 'تم اعتماد طلب التسجيل بنجاح. يمكنك الآن متابعة خطوات البدء.',
            'en' => 'Your registration request was approved. You can now continue with the onboarding steps.',
        ],
        'registration.rejected' => [
            'ar' => 'تعذّر اعتماد طلب التسجيل. راجع تفاصيل الطلب أو تواصل مع الإدارة.',
            'en' => 'Your registration request could not be approved. Review the request details or contact the administration.',
        ],
    ];

    public function up(): void
    {
        foreach (self::PREVIOUS_BODIES as $eventKey => $previousBodies) {
            foreach ($previousBodies as $locale => $previousBody) {
                /** @var array<string, array{subject: string, body: string, parameters?: list<string>}> $templates */
                $templates = Lang::get('notifications::templates', [], $locale);
                $template = $templates[$eventKey] ?? null;

                if (!is_array($template)
                    || !is_string($template['subject'] ?? null)
                    || !is_string($template['body'] ?? null)) {
                    continue;
                }

                DB::table('notification_templates')
                    ->whereNotNull('organization_id')
                    ->where('event_key', $eventKey)
                    ->where('locale', $locale)
                    ->where('body', $previousBody)
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
         * لا تراجع: الرجوع يعيد نص واتساب العربي إلى الرسالة التي لا تخبر
         * الطالب بشيء — وهي سبب هذا التغيير لا حالة سليمة نحفظها.
         */
    }
};

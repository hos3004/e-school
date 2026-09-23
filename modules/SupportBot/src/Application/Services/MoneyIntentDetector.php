<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

use Modules\SupportBot\Domain\Enums\BotTopic;

/**
 * يقرأ **نص السؤال نفسه** بحثًا عن نية مالية، بلا نموذج.
 *
 * يوجد لأن سياج BotTopic يحرس ثلاثة أسماء مواضيع لا ثلاثة معانٍ: لو أقنع أحدهم
 * المصنِّف أن سؤاله عن المستحقات هو «مساعدة في المنصة»، يمر السؤال إلى الصياغة
 * والسياج لا يرى شيئًا. هذا الكاشف لا يمكن إقناعه، لأنه لا يفهم — يطابق فقط.
 *
 * المطابقة خشنة عن قصد: الخطأ بالإرشاد في سؤال بريء كلفته رد مهذّب يدلّ على
 * صفحة، والخطأ بالسماح في سؤال مالي كلفته كسر الشرط الوحيد الذي لا يقبل
 * صاحب المنصة كسره.
 *
 * «أجر» وحدها مستبعدة: في سياق القرآن تعني الثواب، و«ما أجر حفظ سورة الملك»
 * سؤال مشروع لا يجوز أن يُعامل كسؤال عن الراتب.
 */
final readonly class MoneyIntentDetector
{
    /** @var array<string, list<string>> */
    private const array SIGNALS = [
        'session_count' => [
            'الحصص المحتسبة', 'حصص محتسبة', 'عدد الحصص المستحقة', 'الحصص المستحقة',
            'كم حصة محسوبة', 'كام حصة محسوبة', 'محسوبالي', 'محسوبة لي',
        ],
        'payroll_dues' => [
            'مستحقات', 'مستحقاتي', 'مستحقاتك', 'المستحق لي', 'راتب', 'راتبي', 'مرتب', 'مرتبي',
            'فلوس', 'فلوسي', 'أجرتي', 'أجرة', 'قبض', 'اقبض', 'هقبض', 'المبلغ', 'كم آخذ',
            'كام هاخد', 'كم سأتقاضى', 'تحويل الفلوس', 'salary', 'payout', 'my dues', 'my pay',
        ],
        'billing' => [
            'رسوم', 'الرسوم', 'سعر الاشتراك', 'قيمة الاشتراك', 'سعر الدورة', 'تكلفة', 'كم أدفع', 'كام ادفع',
            'fees', 'tuition', 'subscription price',
        ],
    ];

    /**
     * الموضوع المالي الذي يدل عليه النص، أو null إن لم يدل على شيء مالي.
     */
    public function detect(string $message): ?BotTopic
    {
        $normalized = mb_strtolower($this->stripDiacritics($message));

        foreach (self::SIGNALS as $topic => $phrases) {
            foreach ($phrases as $phrase) {
                // العبارة تُطبَّع كالنص، وإلا فاتت «كم آخذ» بعد توحيد الهمزة في المدخل.
                if (str_contains($normalized, mb_strtolower($this->stripDiacritics($phrase)))) {
                    return BotTopic::from($topic);
                }
            }
        }

        return null;
    }

    /**
     * التشكيل والتطويل يكسران المطابقة الحرفية: «مُسْتَحَقَّاتي» و«مستحـقاتي»
     * يجب أن تطابقا «مستحقاتي». وتوحيد الهمزات يجمع «اجرتي» و«أجرتي».
     */
    private function stripDiacritics(string $text): string
    {
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);

        return strtr($text, ['إ' => 'أ', 'آ' => 'أ', 'ٱ' => 'أ']);
    }
}

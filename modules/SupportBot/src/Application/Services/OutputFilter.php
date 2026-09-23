<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

/**
 * الفحص الأخير قبل أن يرى المستخدم الرد.
 *
 * الطبقتان قبله (التصنيف ثم القواعد) تفترضان تصنيفًا صحيحًا. هذا الفحص لا
 * يفترض شيئًا: يقرأ النص الناتج ويسأل هل ذكر مبلغًا أو عددًا يخص الأجر.
 *
 * **معايرة الحساسية هي كل شيء هنا.** فلتر يرفض كل رقم يجعل البوت عديم الفائدة:
 * «حصتك الساعة ٧» و«لديك ٣ حصص هذا الأسبوع» مشروعان تمامًا. لذلك الشرط ليس
 * وجود رقم، بل قرب رقم من علامة عملة أو من كلمة تربطه بالأجر.
 *
 * كل المسافات هنا **بالمحارف لا بالبايت**: الحرف العربي بايتان في UTF-8، فنافذة
 * مقيسة بالبايت تنكمش إلى نصف حجمها المقصود ويفلت منها «٣١٢٥ وهو بالجنيه».
 *
 * عند الالتقاط يُستبدل الرد كاملًا بالنص المعدّ؛ الحذف الجزئي كان سينتج جملة
 * مبتورة تكشف أن شيئًا أُخفي وتدعو إلى السؤال مجددًا بصيغة أخرى.
 */
final readonly class OutputFilter
{
    /** أقصى مسافة بالمحارف بين الرقم وعلامة العملة ليُعدّا مبلغًا واحدًا. */
    private const int CURRENCY_PROXIMITY = 16;

    /**
     * كلمة الأجر تسبق الرقم عادةً بجملة كاملة لا بكلمة: «الحصص المحتسبة لك هذا
     * الشهر ١٢». نافذة العملة القصيرة كانت تفوّتها، فلهذه نافذتها الأوسع.
     */
    private const int PAY_CONTEXT_PROXIMITY = 32;

    /**
     * رقم بالأرقام، أو كلمة مرتبة عددية مكتوبة بالحروف. الثانية لأن نموذجًا
     * طُلب منه «اكتب المبلغ بالحروف» سيكتب «ثلاثة آلاف جنيه» بلا رقم واحد.
     */
    private const string NUMBER_PATTERN =
        '/\d[\d,.\x{060C}\x{066B}\x{066C}]*|آلاف|ألفين|ألفان|ألف|مئتين|مئتان|مائة|مئة|ملايين|مليون/u';

    /**
     * كلمات تجعل أي عدد بجوارها عددًا يخص الأجر، ولو بلا عملة: «الحصص
     * المحتسبة لك ١٢» لا تحمل عملة وهي بالضبط ما طلب صاحب المنصة ألّا يُذكر.
     *
     * «أجر» وحدها مستبعدة عمدًا: في سياق القرآن تعني الثواب، و«ما أجر حفظ
     * سورة» سؤال مشروع لا علاقة له بالمال.
     */
    private const array PAY_CONTEXT = [
        'مستحق', 'محتسب', 'راتب', 'أجرة', 'أجرتك', 'أجرتي', 'الأجر الشهري',
        'مبلغ', 'رصيد', 'مكافأة', 'خصم', 'salary', 'payout', 'dues', 'balance',
    ];

    /**
     * علامات عملة احتياطية. الإعداد المفقود أو الفارغ لا يجوز أن يعطّل الفلتر
     * صامتًا — الإنتاج يعمل بإعداد مخبوء، وصفّ ناقص فيه كان سيمرّر كل مبلغ.
     */
    private const array DEFAULT_CURRENCY_MARKERS = [
        'جنيه', 'ج.م', 'ج م', 'جم', 'دولار', 'ريال', 'درهم', 'دينار', 'يورو',
        'egp', 'usd', 'sar', 'aed', 'eur', '$', '£', '€',
    ];

    public function passes(string $text): bool
    {
        if (!(bool) config('support_bot.output_filter.enabled', true)) {
            return true;
        }

        return !$this->mentionsWithheldFigure($text);
    }

    private function mentionsWithheldFigure(string $text): bool
    {
        $normalized = mb_strtolower($this->normalizeDigits($text));

        if (preg_match_all(self::NUMBER_PATTERN, $normalized, $matches, PREG_OFFSET_CAPTURE) === false) {
            // تعذّر الفحص = لا نعرف، والمجهول هنا يُحجب ولا يُنشر.
            return true;
        }

        $currency = $this->currencyMarkers();

        foreach ($matches[0] as [$number, $byteOffset]) {
            // الإزاحة من preg بالبايت؛ نحوّلها إلى محارف قبل أي قص.
            $start = mb_strlen(substr($normalized, 0, $byteOffset));
            $length = mb_strlen((string) $number);

            if ($this->windowContains($normalized, $start, $length, self::CURRENCY_PROXIMITY, $currency)
                || $this->windowContains($normalized, $start, $length, self::PAY_CONTEXT_PROXIMITY, self::PAY_CONTEXT)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $markers
     */
    private function windowContains(string $text, int $start, int $length, int $radius, array $markers): bool
    {
        $windowStart = max(0, $start - $radius);
        $window = mb_substr($text, $windowStart, ($start - $windowStart) + $length + $radius);

        foreach ($markers as $marker) {
            if ($marker !== '' && str_contains($window, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDigits(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /**
     * @return list<string>
     */
    private function currencyMarkers(): array
    {
        $configured = config('support_bot.output_filter.currency_markers');
        $markers = [];

        if (is_array($configured)) {
            foreach ($configured as $marker) {
                if (is_string($marker) && trim($marker) !== '') {
                    $markers[] = mb_strtolower(trim($marker));
                }
            }
        }

        return array_values(array_unique([...$markers, ...self::DEFAULT_CURRENCY_MARKERS]));
    }
}

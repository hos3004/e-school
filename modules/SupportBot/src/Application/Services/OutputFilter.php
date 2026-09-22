<?php

declare(strict_types=1);

namespace Modules\SupportBot\Application\Services;

/**
 * الفحص الأخير قبل أن يرى المستخدم الرد.
 *
 * الطبقتان قبله (التصنيف ثم القواعد) تفترضان تصنيفًا صحيحًا. هذا الفحص لا
 * يفترض شيئًا: يقرأ النص الناتج ويسأل سؤالًا واحدًا — هل ذكر مبلغًا؟
 *
 * **معايرة الحساسية هي كل شيء هنا.** فلتر يرفض كل رقم يجعل البوت عديم الفائدة:
 * «حصتك الساعة ٧» و«لديك ٣ حصص هذا الأسبوع» كلاهما مشروع تمامًا. لذلك الشرط
 * ليس وجود رقم، بل **قرب رقم من علامة عملة**. هكذا يمر الوقت والعدد، ويُلتقط
 * «٣١٢٥ جنيه».
 *
 * عند الالتقاط يُستبدل الرد كاملًا بالنص المعدّ. الحذف الجزئي كان سينتج جملة
 * مبتورة تكشف للمستخدم أن شيئًا أُخفي، وتدعوه للسؤال مجددًا بصيغة أخرى.
 */
final readonly class OutputFilter
{
    /**
     * أقصى مسافة بالمحارف بين الرقم وعلامة العملة ليُعتبرا مبلغًا واحدًا.
     * تكفي لـ«3125 جنيهًا» و«مبلغ 3125 ج.م» ولا تصل إلى رقم في جملة أخرى.
     */
    private const int PROXIMITY_CHARACTERS = 12;

    public function passes(string $text): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        return !$this->mentionsAmount($text);
    }

    private function mentionsAmount(string $text): bool
    {
        $markers = $this->currencyMarkers();

        if ($markers === []) {
            return false;
        }

        // توحيد الأرقام العربية‑الهندية حتى لا يفلت «٣١٢٥ جنيه» من فحص ASCII.
        $normalized = $this->normalizeDigits($text);

        if (preg_match_all('/\d[\d,.\s]*/u', $normalized, $matches, PREG_OFFSET_CAPTURE) === false) {
            return false;
        }

        foreach ($matches[0] as [$number, $byteOffset]) {
            $start = max(0, $byteOffset - self::PROXIMITY_CHARACTERS);
            $length = strlen((string) $number) + (self::PROXIMITY_CHARACTERS * 2);
            $window = substr($normalized, $start, $length);

            foreach ($markers as $marker) {
                if ($marker !== '' && str_contains($window, $marker)) {
                    return true;
                }
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

        if (!is_array($configured)) {
            return [];
        }

        $markers = [];

        foreach ($configured as $marker) {
            if (is_string($marker) && trim($marker) !== '') {
                $markers[] = trim($marker);
            }
        }

        return $markers;
    }

    private function enabled(): bool
    {
        return (bool) config('support_bot.output_filter.enabled', true);
    }
}

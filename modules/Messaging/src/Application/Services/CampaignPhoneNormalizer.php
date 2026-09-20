<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Services;

/**
 * تطبيع أرقام حملات واتساب.
 *
 * يختلف عن مطبِّع أرقام الملفات الشخصية في نقطة واحدة حاسمة: هناك يُعرف بلد
 * صاحب الملف فيُكمَل الرقم المحلي من بلده، وهنا لا بلد أصلًا — القائمة ترد من
 * ملف أو من لصق، وطلاب المدرسة من جنسيات مختلفة. لذلك لا يُخمَّن كود دولة:
 * رقم محلي بصفر واحد بلا كود يُرفض ويُعاد إلى المرسِل ليصحّحه بنفسه، لأن
 * تخمينه يعني إرسال رسالة إلى شخص آخر تمامًا يحمل الرقم في بلد آخر.
 *
 * ما يُقبل:
 *   +201012345678 → كما هو.
 *   00201012345678 → +201012345678.
 *   201012345678  → +201012345678 (رقم دولي كُتب بلا علامة).
 * ما يُرفض:
 *   01012345678 → phone_missing_country_code.
 */
final class CampaignPhoneNormalizer
{
    /** @var array<string, string> */
    private const EASTERN_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * @return array{phone: string, reason: null}|array{phone: null, reason: non-empty-string}
     */
    public function normalize(string $input): array
    {
        $compact = str_replace(
            [' ', "\t", "\r", "\n", '(', ')', '.', '-', '_', "\u{00a0}"],
            '',
            strtr(trim($input), self::EASTERN_DIGITS),
        );

        if ($compact === '') {
            return $this->rejected('phone_empty');
        }

        if (preg_match('/^\+?\d+$/', $compact) !== 1) {
            return $this->rejected('phone_invalid_characters');
        }

        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }

        /*
         * الصفر الأول بادئة اتصال محلية داخل دولة لا نعرفها. لا يُحذف ولا
         * يُكمَّل بكود مفترض — يُعاد الرقم لصاحبه ليكتبه دوليًا.
         */
        if (str_starts_with($compact, '0')) {
            return $this->rejected('phone_missing_country_code');
        }

        if (!str_starts_with($compact, '+')) {
            $compact = '+'.$compact;
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $compact) !== 1) {
            return $this->rejected('phone_invalid_format');
        }

        return ['phone' => $compact, 'reason' => null];
    }

    /**
     * @param non-empty-string $reason
     * @return array{phone: null, reason: non-empty-string}
     */
    private function rejected(string $reason): array
    {
        return ['phone' => null, 'reason' => $reason];
    }
}

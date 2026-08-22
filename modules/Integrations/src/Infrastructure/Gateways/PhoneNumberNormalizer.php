<?php

declare(strict_types=1);

namespace Modules\Integrations\Infrastructure\Gateways;

use InvalidArgumentException;

/**
 * يطبّع أرقام الاتصال قبل تسليمها للمزوّد حتى لا تُهدر محاولات الشبكة
 * على مدخل لا يطابق E.164.
 */
final class PhoneNumberNormalizer
{
    /**
     * أكواد الدول التي تدعمها ملفات المستخدمين في النطاق الحالي للمرحلة الأولى.
     *
     * @var array<string, non-empty-string>
     */
    private const COUNTRY_CALLING_CODES = [
        'EG' => '20',
        'SA' => '966',
    ];

    public function normalize(string $phone, ?string $countryCode): string
    {
        $phone = $this->normalizeDigits(trim($phone));

        if ($phone === '' || preg_match('/^[0-9+\s().-]+$/u', $phone) !== 1) {
            throw new InvalidArgumentException('invalid_phone_number');
        }

        $compact = str_replace([' ', "\t", "\r", "\n", '(', ')', '.', '-'], '', $phone);

        if (str_starts_with($compact, '00')) {
            $compact = '+'.substr($compact, 2);
        }

        if (str_starts_with($compact, '+')) {
            return $this->assertE164($compact);
        }

        if (preg_match('/^\d+$/', $compact) !== 1) {
            throw new InvalidArgumentException('invalid_phone_number');
        }

        $callingCode = self::COUNTRY_CALLING_CODES[strtoupper(trim((string) $countryCode))] ?? null;

        if ($callingCode === null) {
            throw new InvalidArgumentException('unsupported_phone_country');
        }

        // بعض الملفات القديمة خزّنت الرقم الدولي بلا علامة +؛ لا نكرر كود الدولة.
        if (str_starts_with($compact, $callingCode)) {
            return $this->assertE164('+'.$compact);
        }

        // الصفر الأول بادئة اتصال محلية وليس جزءًا من E.164.
        $nationalNumber = str_starts_with($compact, '0')
            ? substr($compact, 1)
            : $compact;

        return $this->assertE164('+'.$callingCode.$nationalNumber);
    }

    private function assertE164(string $phone): string
    {
        if (preg_match('/^\+[1-9]\d{7,14}$/', $phone) !== 1) {
            throw new InvalidArgumentException('invalid_phone_number');
        }

        return $phone;
    }

    private function normalizeDigits(string $phone): string
    {
        return strtr($phone, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ]);
    }
}

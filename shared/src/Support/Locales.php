<?php

declare(strict_types=1);

namespace Shared\Support;

/**
 * اللغات المعروضة في الواجهات — مصدرها الوحيد `config('app.supported_locales')`.
 *
 * إيقاف لغة أو إعادتها يتم من `APP_SUPPORTED_LOCALES` وحدها، فلا تُكتب قائمة
 * لغات داخل أي واجهة أو قاعدة تحقق. ملفات الترجمة والقيم المخزَّنة تبقى كما هي؛
 * الإيقاف يمنع العرض والاختيار فقط ولا يحذف بيانات.
 */
final class Locales
{
    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        /** @var list<string> $configured */
        $configured = array_values(array_filter(
            array_map(
                static fn (mixed $locale): string => trim((string) $locale),
                (array) config('app.supported_locales', ['ar']),
            ),
            static fn (string $locale): bool => $locale !== '',
        ));

        return $configured === [] ? ['ar'] : $configured;
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::supported(), true);
    }

    /**
     * خيارات قائمة منسدلة: المفتاح رمز اللغة والقيمة تسميتها من ملف ترجمة.
     * `$keyPrefix` مثل `identity::locales.` فيصير مفتاح العربية `identity::locales.ar`.
     *
     * @return array<string, string>
     */
    public static function options(string $keyPrefix): array
    {
        $options = [];

        foreach (self::supported() as $locale) {
            $options[$locale] = (string) __($keyPrefix.$locale);
        }

        return $options;
    }
}

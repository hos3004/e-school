<?php

declare(strict_types=1);

namespace Shared\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * تعامل آمن مع أعمدة `jsonb` المترجمة داخل جداول Filament.
 *
 * السبب: `->searchable()` الافتراضي يبني `WHERE column LIKE ?`، وPostgreSQL
 * لا يعرّف المعامل `~~` على `jsonb`، فينهار الطلب بـ
 * `operator does not exist: jsonb ~~ unknown` — أي **خطأ 500 لحظة كتابة أول
 * حرف في مربع البحث**. الحل هو تحويل العمود إلى نص قبل المطابقة.
 *
 * والفرز الافتراضي لا ينهار لكنه يرتّب حسب بنية JSON لا حسب النص المعروض،
 * فيبدو للمستخدم عشوائيًا؛ لذلك يُفرز بمفتاح اللغة صراحةً.
 */
final class LocalizedJsonColumn
{
    /**
     * بحث غير حسّاس لحالة الأحرف داخل كل ترجمات العمود.
     *
     * البحث في التمثيل النصي الكامل مقصود: المستخدم قد يبحث بالاسم العربي أو
     * الإنجليزي، ولا يجوز أن تحجب لغة الواجهة نتيجة موجودة بلغة أخرى.
     */
    public static function search(string $column): Closure
    {
        return static function (Builder $query, string $search) use ($column): Builder {
            $column = self::assertSafeColumn($column);

            return $query->whereRaw(
                sprintf('%s::text ILIKE ?', $column),
                ['%'.$search.'%'],
            );
        };
    }

    /**
     * فرز بمفتاح اللغة الحالية، مع الرجوع إلى لغة الاحتياط ثم النص الخام.
     */
    public static function sort(string $column, ?string $locale = null): Closure
    {
        return static function (Builder $query, string $direction) use ($column, $locale): Builder {
            $column = self::assertSafeColumn($column);
            $locale = self::assertSafeLocale($locale ?? (string) app()->getLocale());
            $fallback = self::assertSafeLocale((string) config('app.fallback_locale', 'en'));

            /*
             * `$direction` يصل من Filament بقيمة asc أو desc، لكنه يُدمج في
             * SQL خامًا — فيُقيَّد هنا بدل الوثوق بالمصدر.
             */
            $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

            return $query->orderByRaw(
                sprintf(
                    'coalesce(%1$s ->> \'%2$s\', %1$s ->> \'%3$s\', %1$s::text) %4$s',
                    $column,
                    $locale,
                    $fallback,
                    $direction,
                ),
            );
        };
    }

    /**
     * قراءة النص المعروض من قيمة العمود أيًا كان شكلها الواصل.
     *
     * @param mixed $state
     */
    public static function display($state, ?string $locale = null): string
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (!is_array($decoded)) {
                return $state;
            }

            $state = $decoded;
        }

        if (is_object($state)) {
            $state = (array) $state;
        }

        if (!is_array($state)) {
            return '';
        }

        $candidates = [
            $locale ?? (string) app()->getLocale(),
            (string) config('app.fallback_locale', 'en'),
            'ar',
            'en',
        ];

        foreach ($candidates as $candidate) {
            if (isset($state[$candidate]) && is_string($state[$candidate])) {
                return $state[$candidate];
            }
        }

        foreach ($state as $value) {
            if (is_string($value)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * أسماء الأعمدة تأتي من الكود لا من المستخدم، لكن الدالة تبني SQL خامًا —
     * فيبقى الفحص قائمًا حتى لا يتحوّل تمرير خاطئ مستقبلًا إلى حقن.
     */
    private static function assertSafeColumn(string $column): string
    {
        if (preg_match('/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i', $column) !== 1) {
            throw new \InvalidArgumentException("اسم عمود غير صالح: {$column}");
        }

        return $column;
    }

    private static function assertSafeLocale(string $locale): string
    {
        return preg_match('/^[a-z]{2}(_[A-Za-z]{2,4})?$/', $locale) === 1 ? $locale : 'en';
    }
}

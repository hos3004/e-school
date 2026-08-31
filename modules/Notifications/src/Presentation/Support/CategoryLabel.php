<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Support;

/**
 * اسم فئة الإشعار كما يُعرض للأدمن.
 *
 * الفئة تبقى نصًّا حرًّا تحدده الموديولات المالكة للأحداث؛ هذه الطبقة تعرّبها
 * عند العرض فقط. فئة بلا ترجمة تظهر بمفتاحها بدل أن تختفي.
 */
final class CategoryLabel
{
    public static function for(?string $category): string
    {
        if ($category === null || $category === '') {
            return '—';
        }

        $translation = __('notifications::categories.'.$category);

        return is_string($translation) && !str_starts_with($translation, 'notifications::')
            ? $translation
            : $category;
    }

    /**
     * خيارات القوائم: المفتاح مخزَّن، والقيمة معروضة.
     *
     * @param iterable<int, string|null> $categories
     * @return array<string, string>
     */
    public static function options(iterable $categories): array
    {
        $options = [];

        foreach ($categories as $category) {
            if (!is_string($category) || $category === '') {
                continue;
            }

            $options[$category] = self::for($category);
        }

        asort($options);

        return $options;
    }
}

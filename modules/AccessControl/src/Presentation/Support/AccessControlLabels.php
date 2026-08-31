<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Support;

/**
 * تعريب أسماء الصلاحيات والأدوار والموديولات عند العرض فقط.
 *
 * المفتاح الحقيقي (settings.manage · platform_admin · Payroll) لا يتغيّر في
 * قاعدة البيانات ولا في الكود؛ هذه الطبقة تترجمه للعين البشرية في اللوحة.
 *
 * غياب الترجمة يعيد المفتاح كما هو بدل نص فارغ — صلاحية جديدة تظهر بمفتاحها
 * حتى تُترجَم، ولا تختفي عن الأدمن بسبب سطر ناقص في ملف اللغة.
 */
final class AccessControlLabels
{
    public static function permission(?string $name): string
    {
        return self::translate('names', $name);
    }

    public static function role(?string $name): string
    {
        return self::translate('roles', $name);
    }

    public static function module(?string $name): string
    {
        return self::translate('modules', $name);
    }

    /**
     * خيارات الفلاتر: المفتاح كما هو في قاعدة البيانات، والقيمة مترجمة.
     *
     * @param  iterable<int, string|null> $keys
     * @return array<string, string>
     */
    public static function options(string $group, iterable $keys): array
    {
        $options = [];

        foreach ($keys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $options[$key] = self::translate($group, $key);
        }

        asort($options);

        return $options;
    }

    /**
     * البحث في مصفوفة المجموعة مباشرة، لا عبر `__('...group.key')`.
     *
     * السبب: مفاتيح الصلاحيات تحوي نقاطًا (student.create)، ومترجم Laravel
     * يقرأ النقطة كتفرّع داخل المصفوفة فيبحث عن ['names']['student']['create']
     * ولا يجدها. القراءة المباشرة تعامل المفتاح كنص واحد كما هو مخزَّن فعلًا.
     */
    private static function translate(string $group, ?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        $lines = trans('accesscontrol::permissions');

        if (!is_array($lines) || !is_array($lines[$group] ?? null)) {
            return $key;
        }

        $translation = $lines[$group][$key] ?? null;

        return is_string($translation) && $translation !== '' ? $translation : $key;
    }
}

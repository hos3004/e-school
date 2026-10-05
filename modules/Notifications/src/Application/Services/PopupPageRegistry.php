<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

/**
 * سجل الصفحات المسموح استهدافها بـSpecificPage — مفاتيح قانونية فقط.
 *
 * لا URLs مكتوبة يدويًا ولا routes عشوائية: كل مفتاح مربوط بمسار مسمّى
 * موجود فعليًا في المنصة، والتحقق يتم على الخادم عند الحفظ والعرض.
 */
final class PopupPageRegistry
{
    /**
     * مفتاح قانوني ← [المسار المسمّى، مفتاح الترجمة].
     *
     * @return array<string, array{route: string, label_key: string}>
     */
    public static function pages(): array
    {
        return [
            'student.dashboard' => ['route' => 'portal.student.dashboard', 'label_key' => 'notifications::popups.pages.student_dashboard'],
            'student.schedule' => ['route' => 'portal.student.schedule', 'label_key' => 'notifications::popups.pages.student_schedule'],
            'guardian.dashboard' => ['route' => 'portal.guardian.dashboard', 'label_key' => 'notifications::popups.pages.guardian_dashboard'],
            'teacher.dashboard' => ['route' => 'portal.teacher.dashboard', 'label_key' => 'notifications::popups.pages.teacher_dashboard'],
            'admin.dashboard' => ['route' => 'filament.admin.pages.dashboard', 'label_key' => 'notifications::popups.pages.admin_dashboard'],
        ];
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::pages());
    }

    public static function routeFor(string $key): ?string
    {
        return self::pages()[$key]['route'] ?? null;
    }

    /** @return array<string, string> خيارات جاهزة للـSelect */
    public static function options(): array
    {
        return collect(self::pages())
            ->mapWithKeys(static fn (array $page, string $key): array => [$key => __($page['label_key'])])
            ->all();
    }
}

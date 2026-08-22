<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Policies;

use Modules\Academics\Domain\Models\Course;

/**
 * سياسة الكورسات.
 *
 * لا فحص لأسماء الأدوار — الصلاحيات عبر Gate وفق المصفوفة المعلنة.
 */
final class CoursePolicy
{
    public function viewAny($user): bool
    {
        return $user->can('academics.courses.view_any');
    }

    public function view($user, Course $course): bool
    {
        return $user->can('academics.courses.view_any')
            || $user->can('academics.courses.view');
    }

    public function create($user): bool
    {
        return $user->can('academics.courses.create');
    }

    public function update($user, Course $course): bool
    {
        return $user->can('academics.courses.update');
    }

    /** أرشفة كورس — إجراء حسّاس يقتضي سببًا موثّقًا. */
    public function delete($user, Course $course): bool
    {
        return $user->can('academics.courses.archive');
    }

    public function restore($user, Course $course): bool
    {
        return $user->can('academics.courses.restore');
    }
}

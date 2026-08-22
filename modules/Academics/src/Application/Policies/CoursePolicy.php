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
        return $user->can('course.manage');
    }

    public function view($user, Course $course): bool
    {
        return $user->can('course.manage');
    }

    public function create($user): bool
    {
        return $user->can('course.manage');
    }

    public function update($user, Course $course): bool
    {
        return $user->can('course.manage');
    }

    /** أرشفة كورس — إجراء حسّاس يقتضي سببًا موثّقًا. */
    public function delete($user, Course $course): bool
    {
        return $user->can('course.manage');
    }

    public function restore($user, Course $course): bool
    {
        return $user->can('course.manage');
    }
}

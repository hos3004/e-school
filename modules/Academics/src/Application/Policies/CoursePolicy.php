<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Academics\Domain\Models\Course;

/**
 * سياسة الكورسات.
 *
 * لا فحص لأسماء الأدوار — الصلاحيات عبر Gate وفق المصفوفة المعلنة.
 */
final class CoursePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('course.manage');
    }

    public function view(Authenticatable&Authorizable $user, Course $course): bool
    {
        return $user->can('course.manage') && $this->belongsToOrganization($user, (string) $course->organization_id);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('course.manage');
    }

    public function update(Authenticatable&Authorizable $user, Course $course): bool
    {
        return $user->can('course.manage') && $this->belongsToOrganization($user, (string) $course->organization_id);
    }

    /** أرشفة كورس — إجراء حسّاس يقتضي سببًا موثّقًا. */
    public function delete(Authenticatable&Authorizable $user, Course $course): bool
    {
        return $user->can('course.manage') && $this->belongsToOrganization($user, (string) $course->organization_id);
    }

    public function restore(Authenticatable&Authorizable $user, Course $course): bool
    {
        return $user->can('course.manage') && $this->belongsToOrganization($user, (string) $course->organization_id);
    }

    private function belongsToOrganization(Authenticatable&Authorizable $user, string $organizationId): bool
    {
        $actorOrganizationId = data_get($user, 'organization_id');

        return is_string($actorOrganizationId)
            && $actorOrganizationId !== ''
            && hash_equals($actorOrganizationId, $organizationId);
    }
}

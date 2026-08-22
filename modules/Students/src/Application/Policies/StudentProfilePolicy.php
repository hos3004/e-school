<?php

declare(strict_types=1);

namespace Modules\Students\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Students\Domain\Models\StudentProfile;

/**
 * سياسة ملف الطالب.
 *
 * لا فحص لأسماء الأدوار هنا — القرار دائمًا عبر:
 *  - صلاحية معلنة: $user->can('student.action')
 *  - أو ملكية مباشرة للسجل: الطالب يرى ويحدّث ملفه فقط.
 * وفي الحالتين لا تتجاوز الصلاحية حد المؤسسة أبدًا.
 */
final class StudentProfilePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.view.any');
    }

    public function view(Authenticatable&Authorizable $user, StudentProfile $student): bool
    {
        return $this->sameOrganization($user, $student)
            && ($user->can('student.view.any')
                || (string) $user->getAuthIdentifier() === (string) $student->user_id);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.create');
    }

    public function update(Authenticatable&Authorizable $user, StudentProfile $student): bool
    {
        return $this->sameOrganization($user, $student)
            && ($user->can('student.update')
                || (string) $user->getAuthIdentifier() === (string) $student->user_id);
    }

    /** أرشفة الطالب — إجراء حسّاس للمؤسسة فقط. */
    public function delete(Authenticatable&Authorizable $user, StudentProfile $student): bool
    {
        return $this->sameOrganization($user, $student)
            && $user->can('student.update');
    }

    public function restore(Authenticatable&Authorizable $user, StudentProfile $student): bool
    {
        return $this->sameOrganization($user, $student)
            && $user->can('student.update');
    }

    private function sameOrganization(Authenticatable $user, StudentProfile $student): bool
    {
        return (string) data_get($user, 'organization_id') === (string) $student->organization_id;
    }
}

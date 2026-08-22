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
 *  - صلاحية معلنة: $user->can('students.action')
 *  - أو ملكية مباشرة للسجل: الطالب يرى ويحدّث ملفه فقط.
 */
final class StudentProfilePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.view');
    }

    public function view(Authenticatable&Authorizable $user, StudentProfile $student): bool
    {
        return $user->can('student.view')
            || (string) $user->getAuthIdentifier() === (string) $student->user_id;
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.create');
    }

    public function update(Authenticatable&Authorizable $user, StudentProfile $student): bool
    {
        return $user->can('student.update')
            || ((string) $user->getAuthIdentifier() === (string) $student->user_id);
    }

    /** أرشفة الطالب — إجراء حسّاس للمؤسسة فقط. */
    public function delete(Authenticatable&Authorizable $user, StudentProfile $student): bool
    {
        return $user->can('student.update');
    }

    public function restore(Authenticatable&Authorizable $user, StudentProfile $student): bool
    {
        return $user->can('student.update');
    }
}

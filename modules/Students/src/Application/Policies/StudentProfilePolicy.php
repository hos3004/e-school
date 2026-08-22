<?php

declare(strict_types=1);

namespace Modules\Students\Application\Policies;

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
    public function viewAny($user): bool
    {
        return $user->can('students.view_any');
    }

    public function view($user, StudentProfile $student): bool
    {
        return $user->can('students.view_any')
            || (string) $user->getAuthIdentifier() === (string) $student->user_id;
    }

    public function create($user): bool
    {
        return $user->can('students.create');
    }

    public function update($user, StudentProfile $student): bool
    {
        if ($user->can('students.update_any')) {
            return true;
        }

        return $user->can('students.update_own')
            && (string) $user->getAuthIdentifier() === (string) $student->user_id;
    }

    /** أرشفة الطالب — إجراء حسّاس للمؤسسة فقط. */
    public function delete($user, StudentProfile $student): bool
    {
        return $user->can('students.archive_any');
    }

    public function restore($user, StudentProfile $student): bool
    {
        return $user->can('students.restore_any');
    }
}

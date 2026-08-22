<?php

declare(strict_types=1);

namespace Modules\Assignments\Application\Policies;

use Modules\Assignments\Domain\Models\Assignment;

/**
 * سياسة الأنشطة.
 *
 * لا فحص لأدوار — صلاحيات معلنة فقط، مع حصر القراءة على نطاق
 * المؤسسة، والحذف حذفًا ناعمًا لا يجوز بعد وجود تسليمات مرصودة.
 * ملاحظة الحدود: التحقق من «معلم النشاط» يتم داخل إجراءات التطبيق
 * عبر المعرّف المخزّن، لأن ملفات الموظفين ملك لموديول آخر.
 */
final class AssignmentPolicy
{
    /** @param  mixed  $user */
    public function viewAny($user): bool
    {
        return $user !== null && ($user->can('assignment.manage') || $user->can('assignment.submit') || $user->can('assignment.grade'));
    }

    /** @param  mixed  $user */
    public function view($user, Assignment $assignment): bool
    {
        return $user !== null
            && ($user->can('assignment.manage') || $user->can('assignment.submit') || $user->can('assignment.grade'))
            && (string) $user->organization_id === (string) $assignment->organization_id;
    }

    /** @param  mixed  $user */
    public function create($user): bool
    {
        return $user !== null && $user->can('assignment.manage');
    }

    /** @param  mixed  $user */
    public function update($user, Assignment $assignment): bool
    {
        return $user !== null
            && $user->can('assignment.manage')
            && (string) $user->organization_id === (string) $assignment->organization_id;
    }

    /** حذف ناعم فقط — ولا يجوز بعد وجود تسليمات مرصودة. */
    public function delete($user, Assignment $assignment): bool
    {
        if (!$this->update($user, $assignment)) {
            return false;
        }

        return !$assignment->submissions()->graded()->exists();
    }
}

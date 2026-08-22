<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Policies;

use Modules\Groups\Domain\Models\Group;

/**
 * سياسة المجموعات.
 *
 * لا فحص لأسماء الأدوار هنا — القرار دائمًا عبر صلاحية معلنة
 * $user->can('groups.action') حسب مصفوفة الصلاحيات.
 */
final class GroupPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('groups.view_any');
    }

    public function view($user, Group $group): bool
    {
        return $user->can('groups.view_any');
    }

    public function create($user): bool
    {
        return $user->can('groups.create');
    }

    public function update($user, Group $group): bool
    {
        return $user->can('groups.update_any');
    }

    /** أرشفة مجموعة — إجراء حسّاس للمؤسسة. */
    public function delete($user, Group $group): bool
    {
        return $user->can('groups.archive_any');
    }

    public function restore($user, Group $group): bool
    {
        return $user->can('groups.restore_any');
    }

    public function activate($user, Group $group): bool
    {
        return $user->can('groups.activate');
    }

    public function complete($user, Group $group): bool
    {
        return $user->can('groups.complete');
    }

    public function enrollStudent($user, Group $group): bool
    {
        return $user->can('groups.enroll_student');
    }

    public function withdrawStudent($user, Group $group): bool
    {
        return $user->can('groups.withdraw_student');
    }

    public function assignTeacher($user, Group $group): bool
    {
        return $user->can('groups.assign_teacher');
    }

    public function attachProgram($user, Group $group): bool
    {
        return $user->can('groups.attach_program');
    }
}

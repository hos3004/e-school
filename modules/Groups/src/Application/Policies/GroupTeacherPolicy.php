<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Policies;

use Modules\Groups\Domain\Models\GroupTeacher;

/**
 * سياسة إسناد المعلمين للمجموعات.
 */
final class GroupTeacherPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('groups.view_any');
    }

    public function view($user, GroupTeacher $assignment): bool
    {
        return $user->can('groups.view_any');
    }

    public function create($user): bool
    {
        return $user->can('groups.assign_teacher');
    }

    public function update($user, GroupTeacher $assignment): bool
    {
        return $user->can('groups.assign_teacher');
    }

    /** إلغاء الإسناد — تثبيت تاريخ النهاية دون حذف السجل. */
    public function delete($user, GroupTeacher $assignment): bool
    {
        return $user->can('groups.unassign_teacher');
    }
}

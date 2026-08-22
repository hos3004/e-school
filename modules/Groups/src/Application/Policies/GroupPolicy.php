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
        return $user->can('group.view');
    }

    public function view($user, Group $group): bool
    {
        return $user->can('group.view');
    }

    public function create($user): bool
    {
        return $user->can('group.manage');
    }

    public function update($user, Group $group): bool
    {
        return $user->can('group.manage');
    }

    /** أرشفة مجموعة — إجراء حسّاس للمؤسسة. */
    public function delete($user, Group $group): bool
    {
        return $user->can('group.manage');
    }

    public function restore($user, Group $group): bool
    {
        return $user->can('group.manage');
    }

    public function activate($user, Group $group): bool
    {
        return $user->can('group.manage');
    }

    public function complete($user, Group $group): bool
    {
        return $user->can('group.manage');
    }

    public function enrollStudent($user, Group $group): bool
    {
        return $user->can('group.manage');
    }

    public function withdrawStudent($user, Group $group): bool
    {
        return $user->can('group.manage');
    }

    public function assignTeacher($user, Group $group): bool
    {
        return $user->can('group.manage');
    }

    public function attachProgram($user, Group $group): bool
    {
        return $user->can('group.manage');
    }
}

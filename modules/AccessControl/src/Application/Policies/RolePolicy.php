<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Policies;

use Modules\AccessControl\Domain\Models\Role;

/**
 * سياسة الأدوار.
 *
 * أدوار النظام (is_system) مقفلة أمام التعديل والحذف على مستوى
 * السياسة أيضًا — دفاع ثانٍ بعد حراس الإجراءات.
 */
final class RolePolicy
{
    public function viewAny($user): bool
    {
        return $user->can('accesscontrol.roles.view_any');
    }

    public function view($user, Role $role): bool
    {
        return $user->can('accesscontrol.roles.view_any')
            || $user->can('accesscontrol.roles.view');
    }

    public function create($user): bool
    {
        return $user->can('accesscontrol.roles.create');
    }

    public function update($user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $user->can('accesscontrol.roles.update');
    }

    public function delete($user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $user->can('accesscontrol.roles.delete');
    }

    /** مزامنة صلاحيات الدور. */
    public function syncPermissions($user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $user->can('accesscontrol.roles.sync_permissions');
    }

    /** إسناد الدور لنموذج. */
    public function assign($user): bool
    {
        return $user->can('accesscontrol.assignments.assign_role');
    }

    /** سحب الدور من نموذج. */
    public function revoke($user): bool
    {
        return $user->can('accesscontrol.assignments.revoke_role');
    }
}

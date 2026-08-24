<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\AccessControl\Domain\Models\Role;

/**
 * سياسة الأدوار.
 *
 * أدوار النظام (is_system) مقفلة أمام التعديل والحذف على مستوى
 * السياسة أيضًا — دفاع ثانٍ بعد حراس الإجراءات.
 */
final class RolePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accesscontrol.roles.view_any');
    }

    public function view(Authenticatable&Authorizable $user, Role $role): bool
    {
        return $this->isVisibleTo($user, $role)
            && ($user->can('accesscontrol.roles.view_any')
                || $user->can('accesscontrol.roles.view'));
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accesscontrol.roles.create');
    }

    public function update(Authenticatable&Authorizable $user, Role $role): bool
    {
        if ($role->is_system || !$this->belongsToActor($user, $role)) {
            return false;
        }

        return $user->can('accesscontrol.roles.update');
    }

    public function delete(Authenticatable&Authorizable $user, Role $role): bool
    {
        if ($role->is_system || !$this->belongsToActor($user, $role)) {
            return false;
        }

        return $user->can('accesscontrol.roles.delete');
    }

    /** مزامنة صلاحيات الدور. */
    public function syncPermissions(Authenticatable&Authorizable $user, Role $role): bool
    {
        if ($role->is_system || !$this->belongsToActor($user, $role)) {
            return false;
        }

        return $user->can('accesscontrol.roles.sync_permissions');
    }

    /** إسناد الدور لنموذج. */
    public function assign(Authenticatable&Authorizable $user, Role $role): bool
    {
        return $this->isAssignableBy($user, $role)
            && $user->can('accesscontrol.assignments.assign_role');
    }

    /** سحب الدور من نموذج. */
    public function revoke(Authenticatable&Authorizable $user, Role $role): bool
    {
        return $this->isAssignableBy($user, $role)
            && $user->can('accesscontrol.assignments.revoke_role');
    }

    private function isAssignableBy(Authenticatable $user, Role $role): bool
    {
        return $role->organization_id === null || $this->belongsToActor($user, $role);
    }

    private function isVisibleTo(Authenticatable $user, Role $role): bool
    {
        return $role->organization_id === null || $this->belongsToActor($user, $role);
    }

    private function belongsToActor(Authenticatable $user, Role $role): bool
    {
        $organizationId = method_exists($user, 'getAttribute')
            ? $user->getAttribute('organization_id')
            : null;

        return is_string($organizationId)
            && $role->organization_id !== null
            && hash_equals($organizationId, $role->organization_id);
    }
}

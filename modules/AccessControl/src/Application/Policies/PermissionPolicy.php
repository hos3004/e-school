<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Policies;

use Modules\AccessControl\Domain\Models\Permission;

/**
 * سياسة الصلاحيات.
 *
 * ممنوع فحص أسماء الأدوار — القرار عبر مصفوفة الصلاحيات
 * $user->can('accesscontrol.*') حصرًا.
 */
final class PermissionPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('accesscontrol.permissions.view_any');
    }

    public function view($user, Permission $permission): bool
    {
        return $user->can('accesscontrol.permissions.view_any')
            || $user->can('accesscontrol.permissions.view');
    }

    public function create($user): bool
    {
        return $user->can('accesscontrol.permissions.create');
    }

    public function update($user, Permission $permission): bool
    {
        return $user->can('accesscontrol.permissions.update');
    }

    public function delete($user, Permission $permission): bool
    {
        return $user->can('accesscontrol.permissions.delete');
    }

    /** منح الصلاحية مباشرة لنموذج دون وسيط دور. */
    public function grant($user): bool
    {
        return $user->can('accesscontrol.permissions.grant_direct');
    }
}

<?php

declare(strict_types=1);

namespace Modules\AccessControl\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\AccessControl\Domain\Models\Permission;

/**
 * سياسة الصلاحيات.
 *
 * ممنوع فحص أسماء الأدوار — القرار عبر مصفوفة الصلاحيات
 * $user->can('accesscontrol.*') حصرًا.
 */
final class PermissionPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accesscontrol.permissions.view_any');
    }

    public function view(Authenticatable&Authorizable $user, Permission $permission): bool
    {
        return $user->can('accesscontrol.permissions.view_any')
            || $user->can('accesscontrol.permissions.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return false;
    }

    public function update(Authenticatable&Authorizable $user, Permission $permission): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, Permission $permission): bool
    {
        return false;
    }

    /** منح الصلاحية مباشرة لنموذج دون وسيط دور. */
    public function grant(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accesscontrol.permissions.grant_direct');
    }
}

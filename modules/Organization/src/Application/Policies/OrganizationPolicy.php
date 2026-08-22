<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * سياسة المؤسسات.
 *
 * لا فحص لأسماء الأدوار إطلاقًا — الصلاحيات عبر مصفوفة
 * docs/06-permissions-matrix.md وتُفحص بـ can().
 */
final class OrganizationPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('organizations.view_any');
    }

    public function view(Authenticatable $user, mixed $organization): bool
    {
        return $user->can('organizations.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('organizations.create');
    }

    public function update(Authenticatable $user, mixed $organization): bool
    {
        return $user->can('organizations.update');
    }

    public function delete(Authenticatable $user, mixed $organization): bool
    {
        return $user->can('organizations.delete');
    }

    /** إدارة إعدادات المؤسسة (organization_settings). */
    public function manageSettings(Authenticatable $user, mixed $organization): bool
    {
        return $user->can('organizations.manage_settings');
    }
}

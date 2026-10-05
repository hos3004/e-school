<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Certificates\Domain\Models\Badge;

/**
 * سياسة شارات الكتالوج.
 */
final class BadgePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('certificates.badge.view_any');
    }

    public function view(Authenticatable&Authorizable $user, Badge $badge): bool
    {
        return $user->can('certificates.badge.view')
            && $badge->organization_id === data_get($user, 'organization_id');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('certificates.badge.create');
    }

    public function update(Authenticatable&Authorizable $user, Badge $badge): bool
    {
        return $user->can('certificates.badge.update')
            && $badge->organization_id === data_get($user, 'organization_id');
    }

    public function delete(Authenticatable&Authorizable $user, Badge $badge): bool
    {
        return $user->can('certificates.badge.delete')
            && $badge->organization_id === data_get($user, 'organization_id');
    }
}

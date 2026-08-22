<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Policies;

use Modules\Certificates\Domain\Models\Badge;

/**
 * سياسة شارات الكتالوج.
 */
final class BadgePolicy
{
    public function viewAny($user): bool
    {
        return $user->can('certificates.badge.view_any');
    }

    public function view($user, Badge $badge): bool
    {
        return $user->can('certificates.badge.view')
            && $badge->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('certificates.badge.create');
    }

    public function update($user, Badge $badge): bool
    {
        return $user->can('certificates.badge.update')
            && $badge->organization_id === $user->organization_id;
    }

    public function delete($user, Badge $badge): bool
    {
        return $user->can('certificates.badge.delete')
            && $badge->organization_id === $user->organization_id;
    }
}

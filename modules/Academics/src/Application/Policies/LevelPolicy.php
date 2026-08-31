<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Academics\Domain\Models\Level;

/**
 * سياسة المستويات.
 *
 * لا فحص لأسماء الأدوار — الصلاحيات عبر Gate وفق المصفوفة المعلنة.
 */
final class LevelPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('program.manage');
    }

    public function view(Authenticatable&Authorizable $user, Level $level): bool
    {
        return $user->can('program.manage') && $this->belongsToOrganization($user, $level);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('program.manage');
    }

    public function update(Authenticatable&Authorizable $user, Level $level): bool
    {
        return $user->can('program.manage') && $this->belongsToOrganization($user, $level);
    }

    /** إعادة ترتيب مستويات برنامج. */
    public function reorder(Authenticatable&Authorizable $user): bool
    {
        return $user->can('program.manage');
    }

    private function belongsToOrganization(Authenticatable&Authorizable $user, Level $level): bool
    {
        $actorOrganizationId = data_get($user, 'organization_id');
        $levelOrganizationId = $level->program?->organization_id;

        return is_string($actorOrganizationId)
            && $actorOrganizationId !== ''
            && is_string($levelOrganizationId)
            && hash_equals($actorOrganizationId, $levelOrganizationId);
    }
}

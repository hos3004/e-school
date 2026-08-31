<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Academics\Domain\Models\Program;

/**
 * سياسة البرامج.
 *
 * لا فحص لأسماء الأدوار هنا — القرار دائمًا عبر صلاحية معلنة
 * $user->can('academics.programs.*') وفق مصفوفة الصلاحيات.
 */
final class ProgramPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('program.manage');
    }

    public function view(Authenticatable&Authorizable $user, Program $program): bool
    {
        return $user->can('program.manage') && $this->belongsToOrganization($user, (string) $program->organization_id);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('program.manage');
    }

    public function update(Authenticatable&Authorizable $user, Program $program): bool
    {
        return $user->can('program.manage') && $this->belongsToOrganization($user, (string) $program->organization_id);
    }

    /** أرشفة برنامج — إجراء حسّاس يقتضي سببًا موثّقًا. */
    public function delete(Authenticatable&Authorizable $user, Program $program): bool
    {
        return $user->can('program.manage') && $this->belongsToOrganization($user, (string) $program->organization_id);
    }

    public function restore(Authenticatable&Authorizable $user, Program $program): bool
    {
        return $user->can('program.manage') && $this->belongsToOrganization($user, (string) $program->organization_id);
    }

    private function belongsToOrganization(Authenticatable&Authorizable $user, string $organizationId): bool
    {
        $actorOrganizationId = data_get($user, 'organization_id');

        return is_string($actorOrganizationId)
            && $actorOrganizationId !== ''
            && hash_equals($actorOrganizationId, $organizationId);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Academics\Domain\Models\ProgramCategory;

final class ProgramCategoryPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('program.manage');
    }

    public function view(Authenticatable&Authorizable $user, ProgramCategory $category): bool
    {
        return $user->can('program.manage') && $this->sameOrganization($user, $category);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('program.manage');
    }

    public function update(Authenticatable&Authorizable $user, ProgramCategory $category): bool
    {
        return $user->can('program.manage') && $this->sameOrganization($user, $category);
    }

    public function delete(Authenticatable&Authorizable $user, ProgramCategory $category): bool
    {
        return $user->can('program.manage') && $this->sameOrganization($user, $category);
    }

    private function sameOrganization(Authenticatable&Authorizable $user, ProgramCategory $category): bool
    {
        $organizationId = data_get($user, 'organization_id');

        return is_string($organizationId)
            && $organizationId !== ''
            && hash_equals($organizationId, (string) $category->organization_id);
    }
}

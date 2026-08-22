<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Staff\Domain\Models\StaffProfile;

final class StaffProfilePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.view.any');
    }

    public function view(Authenticatable&Authorizable $user, StaffProfile $profile): bool
    {
        if (!$this->sameOrganization($user, $profile)) {
            return false;
        }

        if ((string) $profile->user_id === (string) $user->getAuthIdentifier()) {
            return true;
        }

        return $user->can('staff.view.any');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.contract.update');
    }

    public function update(Authenticatable&Authorizable $user, StaffProfile $profile): bool
    {
        return $this->sameOrganization($user, $profile)
            && $user->can('staff.contract.update');
    }

    public function delete(Authenticatable&Authorizable $user, StaffProfile $profile): bool
    {
        return $this->sameOrganization($user, $profile)
            && $user->can('staff.contract.update');
    }

    public function terminate(Authenticatable&Authorizable $user, StaffProfile $profile): bool
    {
        return $this->sameOrganization($user, $profile)
            && $user->can('staff.contract.update');
    }

    private function sameOrganization(Authenticatable&Authorizable $user, StaffProfile $profile): bool
    {
        $organizationId = data_get($user, 'organization_id');

        return is_string($organizationId)
            && hash_equals($organizationId, (string) $profile->organization_id);
    }
}

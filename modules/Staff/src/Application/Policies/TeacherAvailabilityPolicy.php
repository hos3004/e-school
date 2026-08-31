<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

final class TeacherAvailabilityPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.view');
    }

    public function view(Authenticatable&Authorizable $user, TeacherAvailability $availability): bool
    {
        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->find($availability->staff_profile_id);

        if ($profile === null || !$this->sameOrganization($user, $profile)) {
            return false;
        }

        return (string) $profile->user_id === (string) $user->getAuthIdentifier()
            || $user->can('staff.view.any');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.view');
    }

    public function update(Authenticatable&Authorizable $user, TeacherAvailability $availability): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, TeacherAvailability $availability): bool
    {
        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->find($availability->staff_profile_id);

        return $profile !== null
            && $this->sameOrganization($user, $profile)
            && $user->can('staff.contract.update');
    }

    public function approve(Authenticatable&Authorizable $user, TeacherAvailability $availability): bool
    {
        if (!$user->can('staff.availability.approve')) {
            return false;
        }

        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->find($availability->staff_profile_id);

        return $profile !== null
            && $this->sameOrganization($user, $profile);
    }

    private function sameOrganization(Authenticatable&Authorizable $user, StaffProfile $profile): bool
    {
        $organizationId = data_get($user, 'organization_id');

        return is_string($organizationId)
            && hash_equals($organizationId, (string) $profile->organization_id);
    }
}

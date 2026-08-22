<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

final class TeacherAvailabilityPolicy
{
    public function viewAny($user): bool
    {
        return $user !== null && $user->can('staff.availability.view_any');
    }

    public function view($user, TeacherAvailability $availability): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('staff.availability.view')) {
            return true;
        }

        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->find($availability->staff_profile_id);

        return $profile !== null
            && (string) $profile->user_id === (string) $user->getAuthIdentifier();
    }

    public function create($user): bool
    {
        return $user !== null && $user->can('staff.availability.create');
    }

    public function update($user, TeacherAvailability $availability): bool
    {
        return false;
    }

    public function delete($user, TeacherAvailability $availability): bool
    {
        return $user !== null && $user->can('staff.availability.delete');
    }
}

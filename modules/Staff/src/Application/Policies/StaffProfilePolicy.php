<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Modules\Staff\Domain\Models\StaffProfile;

final class StaffProfilePolicy
{
    public function viewAny($user): bool
    {
        return $user !== null && $user->can('staff.profile.view_any');
    }

    public function view($user, StaffProfile $profile): bool
    {
        if ($user === null) {
            return false;
        }

        if ((string) $profile->user_id === (string) $user->getAuthIdentifier()) {
            return true;
        }

        return $user->can('staff.profile.view');
    }

    public function create($user): bool
    {
        return $user !== null && $user->can('staff.profile.create');
    }

    public function update($user, StaffProfile $profile): bool
    {
        if ($user === null) {
            return false;
        }

        if ((string) $profile->user_id === (string) $user->getAuthIdentifier()) {
            return true;
        }

        return $user->can('staff.profile.update');
    }

    public function delete($user, StaffProfile $profile): bool
    {
        return $user !== null && $user->can('staff.profile.delete');
    }

    public function terminate($user, StaffProfile $profile): bool
    {
        return $user !== null && $user->can('staff.profile.terminate');
    }
}

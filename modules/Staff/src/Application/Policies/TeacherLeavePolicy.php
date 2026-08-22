<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherLeave;

final class TeacherLeavePolicy
{
    public function viewAny($user): bool
    {
        return $user !== null && $user->can('staff.leave.approve');
    }

    public function view($user, TeacherLeave $leave): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('staff.view')) {
            return true;
        }

        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->find($leave->staff_profile_id);

        return $profile !== null
            && (string) $profile->user_id === (string) $user->getAuthIdentifier();
    }

    public function create($user): bool
    {
        return $user !== null && $user->can('staff.leave.approve');
    }

    public function update($user, TeacherLeave $leave): bool
    {
        return false;
    }

    public function delete($user, TeacherLeave $leave): bool
    {
        return false;
    }

    public function decide($user, TeacherLeave $leave): bool
    {
        return $user !== null && $user->can('staff.leave.approve');
    }
}

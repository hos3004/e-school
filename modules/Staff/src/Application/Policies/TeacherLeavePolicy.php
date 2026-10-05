<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherLeave;

final class TeacherLeavePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.leave.approve');
    }

    public function view(Authenticatable&Authorizable $user, TeacherLeave $leave): bool
    {
        if ($user->can('staff.view')) {
            return true;
        }

        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->find($leave->staff_profile_id);

        return $profile !== null
            && (string) $profile->user_id === (string) $user->getAuthIdentifier();
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.leave.approve');
    }

    /** قرار الإجازة ينتقل عبر decide() وحده، لا بتعديل حر للسجل. */
    public function update(Authenticatable&Authorizable $user, TeacherLeave $leave): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, TeacherLeave $leave): bool
    {
        return false;
    }

    public function decide(Authenticatable&Authorizable $user, TeacherLeave $leave): bool
    {
        return $user->can('staff.leave.approve');
    }
}

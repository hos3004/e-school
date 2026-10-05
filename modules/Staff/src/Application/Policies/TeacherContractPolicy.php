<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherContract;

final class TeacherContractPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.contract.view');
    }

    public function view(Authenticatable&Authorizable $user, TeacherContract $contract): bool
    {
        if ($user->can('staff.contract.view')) {
            return true;
        }

        // صاحب العقد يرى عقده دون صلاحية إدارية.
        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->find($contract->staff_profile_id);

        return $profile !== null
            && (string) $profile->user_id === (string) $user->getAuthIdentifier();
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.contract.update');
    }

    public function update(Authenticatable&Authorizable $user, TeacherContract $contract): bool
    {
        return $user->can('staff.contract.update');
    }

    public function delete(Authenticatable&Authorizable $user, TeacherContract $contract): bool
    {
        return false;
    }

    public function addRate(Authenticatable&Authorizable $user, TeacherContract $contract): bool
    {
        return $user->can('staff.contract.update');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Modules\Staff\Domain\Models\TeacherContract;

final class TeacherContractPolicy
{
    public function viewAny($user): bool
    {
        return $user !== null && $user->can('staff.contract.view_any');
    }

    public function view($user, TeacherContract $contract): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('staff.contract.view')) {
            return true;
        }

        // صاحب العقد يرى عقده دون صلاحية إدارية.
        /** @var \Modules\Staff\Domain\Models\StaffProfile|null $profile */
        $profile = \Modules\Staff\Domain\Models\StaffProfile::query()->find($contract->staff_profile_id);

        return $profile !== null
            && (string) $profile->user_id === (string) $user->getAuthIdentifier();
    }

    public function create($user): bool
    {
        return $user !== null && $user->can('staff.contract.create');
    }

    public function update($user, TeacherContract $contract): bool
    {
        return $user !== null && $user->can('staff.contract.update');
    }

    public function delete($user, TeacherContract $contract): bool
    {
        return false;
    }

    public function addRate($user, TeacherContract $contract): bool
    {
        return $user !== null && $user->can('staff.rate.create');
    }
}

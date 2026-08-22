<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Modules\Staff\Domain\Models\TeacherRate;

final class TeacherRatePolicy
{
    public function viewAny($user): bool
    {
        return $user !== null && $user->can('staff.contract.view');
    }

    public function view($user, TeacherRate $rate): bool
    {
        return $user !== null && $user->can('staff.contract.view');
    }

    public function create($user): bool
    {
        return $user !== null && $user->can('staff.contract.update');
    }

    public function update($user, TeacherRate $rate): bool
    {
        return false;
    }

    public function delete($user, TeacherRate $rate): bool
    {
        return false;
    }
}

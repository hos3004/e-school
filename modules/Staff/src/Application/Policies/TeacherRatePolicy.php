<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Staff\Domain\Models\TeacherRate;

final class TeacherRatePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.contract.view');
    }

    public function view(Authenticatable&Authorizable $user, TeacherRate $rate): bool
    {
        return $user->can('staff.contract.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('staff.contract.update');
    }

    /** الأسعار دفتر append-only — التصحيح بسعر جديد نافذ لا بتعديل سجل قائم. */
    public function update(Authenticatable&Authorizable $user, TeacherRate $rate): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, TeacherRate $rate): bool
    {
        return false;
    }
}

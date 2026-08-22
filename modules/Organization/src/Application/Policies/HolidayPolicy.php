<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Organization\Domain\Models\Holiday;

/**
 * سياسة العطل — الصلاحيات عبر can() فقط.
 */
final class HolidayPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('holidays.view_any');
    }

    public function view(Authenticatable $user, Holiday $holiday): bool
    {
        return $user->can('holidays.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('holidays.create');
    }

    public function update(Authenticatable $user, Holiday $holiday): bool
    {
        return $user->can('holidays.update');
    }

    public function delete(Authenticatable $user, Holiday $holiday): bool
    {
        return $user->can('holidays.delete');
    }
}

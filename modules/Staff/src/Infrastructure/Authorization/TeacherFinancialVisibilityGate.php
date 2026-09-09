<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Authorization;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Staff\Domain\Models\StaffProfile;

final class TeacherFinancialVisibilityGate
{
    public function check(Authenticatable&Authorizable $user, string $ability): ?bool
    {
        if (!in_array($ability, ['payroll.view', 'payroll.export', 'staff.contract.view'], true) || $user->can('admin.panel.access')) {
            return null;
        }
        $hidden = StaffProfile::query()->where('organization_id', $user->getAttribute('organization_id'))->where('user_id', $user->getAuthIdentifier())->where('financials_visible', false)->exists();

        return $hidden ? false : null;
    }
}

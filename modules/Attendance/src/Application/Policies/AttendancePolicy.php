<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Attendance\Domain\Models\Attendance;

/**
 * سياسة قيود الحضور.
 *
 * لا فحص لأسماء الأدوار — القرار عبر صلاحيات معلنة:
 *  - attendance.view       : رؤية سجل الحضور
 *  - attendance.record     : الرصد الأولي واعتماد المعلم للحالة المشتقة
 *  - attendance.override   : تجاوز الحالة بسبب موثّق (إدارة)
 */
final class AttendancePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return $user->can('attendance.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('attendance.record');
    }

    public function update(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return $user->can('attendance.override');
    }

    public function delete(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return $user->can('attendance.override');
    }

    /** اعتماد الحالة المشتقة — للمعلم. */
    public function confirm(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return $user->can('attendance.record');
    }

    /** تجاوز الحالة بسبب موثّق — للإدارة. */
    public function override(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return $user->can('attendance.override');
    }
}

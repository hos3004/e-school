<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Policies;

use Modules\Attendance\Domain\Models\Attendance;

/**
 * سياسة قيود الحضور.
 *
 * لا فحص لأسماء الأدوار — القرار عبر صلاحيات معلنة:
 *  - attendance.view_any   : رؤية سجل الحضور
 *  - attendance.record     : الرصد الأولي من أحداث الفصل
 *  - attendance.confirm    : اعتماد المعلم للحالة المشتقة
 *  - attendance.override   : تجاوز الحالة بسبب موثّق (إدارة)
 *  - attendance.delete_any : حذف القيد (تصحيح خطأ إدخال جسيم فقط)
 */
final class AttendancePolicy
{
    public function viewAny($user): bool
    {
        return $user->can('attendance.view_any');
    }

    public function view($user, Attendance $attendance): bool
    {
        return $user->can('attendance.view_any');
    }

    public function create($user): bool
    {
        return $user->can('attendance.record');
    }

    public function update($user, Attendance $attendance): bool
    {
        return $user->can('attendance.override');
    }

    public function delete($user, Attendance $attendance): bool
    {
        return $user->can('attendance.delete_any');
    }

    /** اعتماد الحالة المشتقة — للمعلم. */
    public function confirm($user, Attendance $attendance): bool
    {
        return $user->can('attendance.confirm');
    }

    /** تجاوز الحالة بسبب موثّق — للإدارة. */
    public function override($user, Attendance $attendance): bool
    {
        return $user->can('attendance.override');
    }
}

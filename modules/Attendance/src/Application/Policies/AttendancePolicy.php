<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;

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
    public function __construct(private readonly SessionParticipantAdministrationQueries $participants) {}

    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return $user->can('attendance.view') && $this->belongsToUserOrganization($user, $attendance);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('attendance.record');
    }

    public function update(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return false;
    }

    /** اعتماد الحالة المشتقة — للمعلم. */
    public function confirm(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return $user->can('attendance.record') && $this->belongsToUserOrganization($user, $attendance);
    }

    /** تجاوز الحالة بسبب موثّق — للإدارة. */
    public function override(Authenticatable&Authorizable $user, Attendance $attendance): bool
    {
        return $user->can('attendance.override') && $this->belongsToUserOrganization($user, $attendance);
    }

    private function belongsToUserOrganization(
        Authenticatable&Authorizable $user,
        Attendance $attendance,
    ): bool {
        $organizationId = $user->getAttribute('organization_id');

        return is_string($organizationId)
            && $organizationId !== ''
            && $this->participants->findForOrganization(
                $organizationId,
                (string) $attendance->session_participant_id,
            ) !== null;
    }
}

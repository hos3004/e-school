<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\AcademicReports\Domain\Models\SessionReport;

/**
 * سياسة تقارير الحصص.
 *
 * جداول التقارير بلا عمود مؤسسة — نطاق الرؤية يُطبَّق عبر scopeForStaff
 * في الاستعلامات، والصلاحيات هنا عبر البوابة فقط.
 */
final class SessionReportPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('session_report.view');
    }

    public function view(Authenticatable&Authorizable $user, SessionReport $report): bool
    {
        return $user->can('session_report.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('session_report.create');
    }

    public function update(Authenticatable&Authorizable $user, SessionReport $report): bool
    {
        return $user->can('session_report.create')
            && $report->staff_profile_id === (string) data_get($user, 'staff_profile_id');
    }

    public function delete(Authenticatable&Authorizable $user, SessionReport $report): bool
    {
        return false;
    }

    /** من يملك إضافة الملاحظة الخاصة بالمشرف على التقرير. */
    public function annotate(Authenticatable&Authorizable $user, SessionReport $report): bool
    {
        return false;
    }
}

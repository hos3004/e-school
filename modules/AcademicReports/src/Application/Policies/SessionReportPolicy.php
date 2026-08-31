<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Policies;

use Modules\AcademicReports\Domain\Models\SessionReport;

/**
 * سياسة تقارير الحصص.
 *
 * جداول التقارير بلا عمود مؤسسة — نطاق الرؤية يُطبَّق عبر scopeForStaff
 * في الاستعلامات، والصلاحيات هنا عبر البوابة فقط.
 */
final class SessionReportPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('session_report.view')
            || $user->can('academicreports.session_report.view_any');
    }

    public function view($user, SessionReport $report): bool
    {
        return $user->can('session_report.view');
    }

    public function create($user): bool
    {
        return $user->can('academicreports.session_report.create');
    }

    public function update($user, SessionReport $report): bool
    {
        return $user->can('academicreports.session_report.update')
            && $report->staff_profile_id === (string) $user->staff_profile_id;
    }

    public function delete($user, SessionReport $report): bool
    {
        return $user->can('academicreports.session_report.delete');
    }

    /** من يملك إضافة الملاحظة الخاصة بالمشرف على التقرير. */
    public function annotate($user, SessionReport $report): bool
    {
        return $user->can('academicreports.session_report.annotate');
    }
}

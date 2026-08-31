<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Policies;

use Modules\AcademicReports\Domain\Models\MonthlyReport;

/**
 * سياسة التقارير الشهرية — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات academicreports.monthly_report.<action>
 * مع مقارنة ملكية السجل بالمؤسسة.
 */
final class MonthlyReportPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('report.view')
            || $user->can('academicreports.monthly_report.view_any');
    }

    public function view($user, MonthlyReport $report): bool
    {
        return $user->can('academicreports.monthly_report.view')
            && $report->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('academicreports.monthly_report.create');
    }

    public function update($user, MonthlyReport $report): bool
    {
        return $user->can('academicreports.monthly_report.update')
            && $report->organization_id === $user->organization_id;
    }

    public function delete($user, MonthlyReport $report): bool
    {
        return $user->can('academicreports.monthly_report.delete')
            && $report->organization_id === $user->organization_id;
    }

    public function approve($user, MonthlyReport $report): bool
    {
        return $user->can('academicreports.monthly_report.approve')
            && $report->organization_id === $user->organization_id;
    }

    public function send($user, MonthlyReport $report): bool
    {
        return $user->can('academicreports.monthly_report.send')
            && $report->organization_id === $user->organization_id;
    }
}

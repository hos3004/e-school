<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Policies;

use Modules\AcademicReports\Domain\Models\SessionReportStudent;

/**
 * سياسة تقييمات الطلاب داخل تقارير الحصص.
 *
 * السجل يرث ملكيته من تقرير الحصة الأب — الفحص هنا بوابة الصلاحيات فقط.
 */
final class SessionReportStudentPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('session_report.view');
    }

    public function view($user, SessionReportStudent $record): bool
    {
        return $user->can('session_report.view');
    }

    public function create($user): bool
    {
        return $user->can('session_report.create');
    }

    public function update($user, SessionReportStudent $record): bool
    {
        return $user->can('session_report.create');
    }

    public function delete($user, SessionReportStudent $record): bool
    {
        return false;
    }
}

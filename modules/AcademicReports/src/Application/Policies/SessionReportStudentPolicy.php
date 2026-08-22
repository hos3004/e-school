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
        return $user->can('academicreports.session_report_student.view_any');
    }

    public function view($user, SessionReportStudent $record): bool
    {
        return $user->can('academicreports.session_report_student.view');
    }

    public function create($user): bool
    {
        return $user->can('academicreports.session_report_student.create');
    }

    public function update($user, SessionReportStudent $record): bool
    {
        return $user->can('academicreports.session_report_student.update');
    }

    public function delete($user, SessionReportStudent $record): bool
    {
        return $user->can('academicreports.session_report_student.delete');
    }
}

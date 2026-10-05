<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;

/**
 * سياسة تقييمات الطلاب داخل تقارير الحصص.
 *
 * السجل يرث ملكيته من تقرير الحصة الأب — الفحص هنا بوابة الصلاحيات فقط.
 */
final class SessionReportStudentPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('session_report.view');
    }

    public function view(Authenticatable&Authorizable $user, SessionReportStudent $record): bool
    {
        return $user->can('session_report.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('session_report.create');
    }

    public function update(Authenticatable&Authorizable $user, SessionReportStudent $record): bool
    {
        return $user->can('session_report.create');
    }

    public function delete(Authenticatable&Authorizable $user, SessionReportStudent $record): bool
    {
        return false;
    }
}

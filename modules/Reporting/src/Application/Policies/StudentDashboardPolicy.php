<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Reporting\Domain\Models\StudentDashboard;

/**
 * سياسة لوحات الطلاب — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * قائمة المؤسسة تحتاج report.view مع student.view.any معًا، والتصحيح
 * يبقى عبر صلاحية خاصة منفصلة ومقارنة ملكية السجل للمؤسسة.
 */
final class StudentDashboardPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('report.view')
            && $user->can('student.view.any');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, StudentDashboard $dashboard): bool
    {
        return $user->can('reporting.student.view')
            && $dashboard->organization_id === $user->organization_id;
    }

    /** اللوحات Read Models — تُبنى بالأحداث لا يدويًا. */
    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    /** التحديث اليدوي الوحيد المسموح هو التصحيح الموثّق بسبب مكتوب. */
    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, StudentDashboard $dashboard): bool
    {
        return $this->correct($user, $dashboard);
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, StudentDashboard $dashboard): bool
    {
        return $user->can('reporting.student.delete')
            && $dashboard->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function correct(Authenticatable $user, StudentDashboard $dashboard): bool
    {
        return $user->can('reporting.student.correct')
            && $dashboard->organization_id === $user->organization_id;
    }
}

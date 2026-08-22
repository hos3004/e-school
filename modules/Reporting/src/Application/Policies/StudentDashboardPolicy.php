<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Modules\Reporting\Domain\Models\StudentDashboard;

/**
 * سياسة لوحات الطلاب — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * القراءة عبر بوابة reporting.student.view_any مع مقارنة ملكية السجل
 * للمؤسسة، والتصحيح عبر صلاحية خاصة منفصلة.
 */
final class StudentDashboardPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('reporting.student.view_any');
    }

    public function view($user, StudentDashboard $dashboard): bool
    {
        return $user->can('reporting.student.view')
            && $dashboard->organization_id === $user->organization_id;
    }

    /** اللوحات Read Models — تُبنى بالأحداث لا يدويًا. */
    public function create($user): bool
    {
        return false;
    }

    /** التحديث اليدوي الوحيد المسموح هو التصحيح الموثّق بسبب مكتوب. */
    public function update($user, StudentDashboard $dashboard): bool
    {
        return $this->correct($user, $dashboard);
    }

    public function delete($user, StudentDashboard $dashboard): bool
    {
        return $user->can('reporting.student.delete')
            && $dashboard->organization_id === $user->organization_id;
    }

    public function correct($user, StudentDashboard $dashboard): bool
    {
        return $user->can('reporting.student.correct')
            && $dashboard->organization_id === $user->organization_id;
    }
}

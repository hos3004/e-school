<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Modules\Reporting\Domain\Models\TeacherDashboard;

/**
 * سياسة لوحات المعلمين — صلاحيات معلنة مع مقارنة ملكية المؤسسة.
 */
final class TeacherDashboardPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('reporting.teacher.view_any');
    }

    public function view($user, TeacherDashboard $dashboard): bool
    {
        return $user->can('reporting.teacher.view')
            && $dashboard->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, TeacherDashboard $dashboard): bool
    {
        return $user->can('reporting.teacher.correct')
            && $dashboard->organization_id === $user->organization_id;
    }

    public function delete($user, TeacherDashboard $dashboard): bool
    {
        return $user->can('reporting.teacher.delete')
            && $dashboard->organization_id === $user->organization_id;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Reporting\Domain\Models\TeacherDashboard;

/**
 * سياسة لوحات المعلمين — صلاحيات معلنة مع مقارنة ملكية المؤسسة.
 */
final class TeacherDashboardPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('report.view')
            && $user->can('staff.view.any');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, TeacherDashboard $dashboard): bool
    {
        return $user->can('reporting.teacher.view')
            && $dashboard->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, TeacherDashboard $dashboard): bool
    {
        return $user->can('reporting.teacher.correct')
            && $dashboard->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, TeacherDashboard $dashboard): bool
    {
        return $user->can('reporting.teacher.delete')
            && $dashboard->organization_id === $user->organization_id;
    }
}

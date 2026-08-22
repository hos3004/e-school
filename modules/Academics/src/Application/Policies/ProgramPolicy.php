<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Policies;

use Modules\Academics\Domain\Models\Program;

/**
 * سياسة البرامج.
 *
 * لا فحص لأسماء الأدوار هنا — القرار دائمًا عبر صلاحية معلنة
 * $user->can('academics.programs.*') وفق مصفوفة الصلاحيات.
 */
final class ProgramPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('academics.programs.view_any');
    }

    public function view($user, Program $program): bool
    {
        return $user->can('academics.programs.view_any')
            || $user->can('academics.programs.view');
    }

    public function create($user): bool
    {
        return $user->can('academics.programs.create');
    }

    public function update($user, Program $program): bool
    {
        return $user->can('academics.programs.update');
    }

    /** أرشفة برنامج — إجراء حسّاس يقتضي سببًا موثّقًا. */
    public function delete($user, Program $program): bool
    {
        return $user->can('academics.programs.archive');
    }

    public function restore($user, Program $program): bool
    {
        return $user->can('academics.programs.restore');
    }
}

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
        return $user->can('program.manage');
    }

    public function view($user, Program $program): bool
    {
        return $user->can('program.manage');
    }

    public function create($user): bool
    {
        return $user->can('program.manage');
    }

    public function update($user, Program $program): bool
    {
        return $user->can('program.manage');
    }

    /** أرشفة برنامج — إجراء حسّاس يقتضي سببًا موثّقًا. */
    public function delete($user, Program $program): bool
    {
        return $user->can('program.manage');
    }

    public function restore($user, Program $program): bool
    {
        return $user->can('program.manage');
    }
}

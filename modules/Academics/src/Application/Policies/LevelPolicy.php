<?php

declare(strict_types=1);

namespace Modules\Academics\Application\Policies;

use Modules\Academics\Domain\Models\Level;

/**
 * سياسة المستويات.
 *
 * لا فحص لأسماء الأدوار — الصلاحيات عبر Gate وفق المصفوفة المعلنة.
 */
final class LevelPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('program.manage');
    }

    public function view($user, Level $level): bool
    {
        return $user->can('program.manage');
    }

    public function create($user): bool
    {
        return $user->can('program.manage');
    }

    public function update($user, Level $level): bool
    {
        return $user->can('program.manage');
    }

    /** إعادة ترتيب مستويات برنامج. */
    public function reorder($user): bool
    {
        return $user->can('program.manage');
    }
}

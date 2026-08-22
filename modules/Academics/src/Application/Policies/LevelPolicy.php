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
        return $user->can('academics.levels.view_any');
    }

    public function view($user, Level $level): bool
    {
        return $user->can('academics.levels.view_any')
            || $user->can('academics.levels.view');
    }

    public function create($user): bool
    {
        return $user->can('academics.levels.create');
    }

    public function update($user, Level $level): bool
    {
        return $user->can('academics.levels.update');
    }

    /** إعادة ترتيب مستويات برنامج. */
    public function reorder($user): bool
    {
        return $user->can('academics.levels.reorder');
    }
}

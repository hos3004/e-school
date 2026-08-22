<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Policies;

use Modules\Discipline\Domain\Models\DisciplineAction;

/**
 * سياسة قيود إجراءات الانضباط — سجل تاريخي للقراءة فقط.
 *
 * القيود تُنشأ آليًا من محرّك التصعيد أو يدويًا بصلاحية خاصة،
 * ولا تعديل ولا حذف عليها بعد إنشائها.
 */
final class DisciplineActionPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('discipline.view_any');
    }

    public function view($user, DisciplineAction $action): bool
    {
        return $user->can('discipline.view_any');
    }

    /** تطبيق إجراء يدوي خارج السُلَّم الآلي. */
    public function create($user): bool
    {
        return $user->can('discipline.apply_actions');
    }

    public function update($user, DisciplineAction $action): bool
    {
        return false;
    }

    public function delete($user, DisciplineAction $action): bool
    {
        return false;
    }
}

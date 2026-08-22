<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Discipline\Domain\Models\DisciplineAction;

/**
 * سياسة قيود إجراءات الانضباط — سجل تاريخي للقراءة فقط.
 *
 * القيود تُنشأ آليًا من محرّك التصعيد أو يدويًا بصلاحية خاصة،
 * ولا تعديل ولا حذف عليها بعد إنشائها.
 */
final class DisciplineActionPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('discipline.view_any');
    }

    public function view(Authenticatable&Authorizable $user, DisciplineAction $action): bool
    {
        return $user->can('discipline.view_any');
    }

    /** تطبيق إجراء يدوي خارج السُلَّم الآلي. */
    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('discipline.apply_actions');
    }

    public function update(Authenticatable&Authorizable $user, DisciplineAction $action): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, DisciplineAction $action): bool
    {
        return false;
    }
}

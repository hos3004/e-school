<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Discipline\Domain\Models\ViolationEvent;

/**
 * سياسة أحداث المخالفات.
 *
 * لا فحص لأدوار — صلاحيات معلنة فقط. المخالفة سجل إداري:
 * القراءة للإشراف، والإنشاء آلي/للمشرفين، والعفو بصلاحية مستقلة موثّقة،
 * ولا تحديث ولا حذف إطلاقًا (ledger-like).
 */
final class ViolationEventPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('discipline.view_any');
    }

    public function view(Authenticatable&Authorizable $user, ViolationEvent $violation): bool
    {
        return $user->can('discipline.view_any');
    }

    /** تسجيل مخالفة يدويًا — الإجراء الآلي يتحقق من نفس الصلاحية. */
    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('discipline.record_violations');
    }

    /** السجل غير قابل للتعديل. */
    public function update(Authenticatable&Authorizable $user, ViolationEvent $violation): bool
    {
        return false;
    }

    /** السجل غير قابل للحذف — العفو هو المسار الوحيد. */
    public function delete(Authenticatable&Authorizable $user, ViolationEvent $violation): bool
    {
        return false;
    }

    /** العفو عن المخالفة — قرار إداري بسببه الإلزامي. */
    public function waive(Authenticatable&Authorizable $user, ViolationEvent $violation): bool
    {
        if ($violation->isWaived()) {
            return false;
        }

        return $user->can('discipline.waive_violations');
    }
}

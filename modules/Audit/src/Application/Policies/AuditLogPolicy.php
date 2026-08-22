<?php

declare(strict_types=1);

namespace Modules\Audit\Application\Policies;

use Modules\Audit\Domain\Models\AuditLog;

/**
 * سياسة قيود التدقيق.
 *
 * ممنوع فحص أسماء الأدوار — الصلاحيات عبر مصفوفة docs/06-permissions-matrix.md
 * و Gate::allows('audit.*'). ملكية السجل تُقارن بالمعرّفات مباشرة.
 *
 * دفتر التدقيق append-only: update و delete مرفوضان دائمًا على مستوى
 * السياسة؛ التصحيح بقيدة جديدة، والتقادم عبر PruneExpiredEntriesAction فقط.
 */
final class AuditLogPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('audit.view_any');
    }

    public function view($user, AuditLog $entry): bool
    {
        if ($user->can('audit.view_any')) {
            return true;
        }

        return $user->can('audit.view') && $user->getAuthIdentifier() === $entry->actor_id;
    }

    public function create($user): bool
    {
        return $user->can('audit.record');
    }

    /** دفتر أقرّ — لا تعديل إطلاقًا. */
    public function update($user, AuditLog $entry): bool
    {
        return false;
    }

    /** دفتر أقرّ — لا حذف فردي إطلاقًا؛ التقادم الدوري فقط. */
    public function delete($user, AuditLog $entry): bool
    {
        return false;
    }

    /** حذف القيود الأقدم من مدة الاحتفاظ — عملية تقادم معتمدة. */
    public function prune($user): bool
    {
        return $user->can('audit.prune');
    }

    /** تصدير القيود (CSV/JSON) للتدقيق الخارجي. */
    public function export($user): bool
    {
        return $user->can('audit.export');
    }
}

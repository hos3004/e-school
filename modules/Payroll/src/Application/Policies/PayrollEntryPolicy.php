<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Policies;

use Modules\Payroll\Domain\Models\PayrollEntry;

/**
 * سياسة قيود دفتر المستحقات.
 *
 * الدفتر append-only: لا تعديل ولا حذف لأي قيدة مهما كانت الصلاحية —
 * التصحيح بقيدة تسوية جديدة فقط.
 */
final class PayrollEntryPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('payroll.entries.view_any');
    }

    public function view($user, PayrollEntry $entry): bool
    {
        return $user->can('payroll.entries.view_any')
            || $user->can('payroll.entries.view');
    }

    /** إنشاء القيود يحدث آليًا عند إقفال الحصص، لا يدويًا. */
    public function create($user): bool
    {
        return false;
    }

    public function update($user, PayrollEntry $entry): bool
    {
        return false;
    }

    public function delete($user, PayrollEntry $entry): bool
    {
        return false;
    }

    /** تحرير القيود المؤجَّلة عند إقامة حصة التلافي. */
    public function release($user): bool
    {
        return $user->can('payroll.entries.release');
    }
}

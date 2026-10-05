<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Payroll\Domain\Models\PayrollEntry;

/**
 * سياسة قيود دفتر المستحقات.
 *
 * الدفتر append-only: لا تعديل ولا حذف لأي قيدة مهما كانت الصلاحية —
 * التصحيح بقيدة تسوية جديدة فقط.
 */
final class PayrollEntryPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('payroll.view');
    }

    public function view(Authenticatable $user, PayrollEntry $entry): bool
    {
        return $user->can('payroll.view')
            && (string) $entry->organization_id === (string) $user->getAttribute('organization_id');
    }

    /** إنشاء القيود يحدث آليًا عند إقفال الحصص، لا يدويًا. */
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, PayrollEntry $entry): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, PayrollEntry $entry): bool
    {
        return false;
    }

    /** تحرير القيود المؤجَّلة عند إقامة حصة التلافي. */
    public function release(Authenticatable $user): bool
    {
        return $user->can('payroll.calculate');
    }
}

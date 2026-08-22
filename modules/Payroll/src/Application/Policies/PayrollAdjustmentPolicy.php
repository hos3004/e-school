<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Payroll\Domain\Models\PayrollAdjustment;

/**
 * سياسة التسويات.
 *
 * قرار العميل: صلاحيتان منفصلتان — propose للاقتراح و approve للاعتماد،
 * ومن يقترح لا يعتمد. لا فحص لأسماء الأدوار.
 */
final class PayrollAdjustmentPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('payroll.adjustments.view_any');
    }

    public function view(Authenticatable $user, PayrollAdjustment $adjustment): bool
    {
        return $user->can('payroll.adjustments.view_any')
            || $user->can('payroll.adjustments.view')
            || (string) $adjustment->proposed_by === (string) $user->getAuthIdentifier();
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can((string) config('payroll.adjustments.propose_permission'));
    }

    /** التسوية المقترحة قيد معلّق لا يُعدَّل — تُرفض وتُقترح بديلة. */
    public function update(Authenticatable $user, PayrollAdjustment $adjustment): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, PayrollAdjustment $adjustment): bool
    {
        return false;
    }

    public function approve(Authenticatable $user, PayrollAdjustment $adjustment): bool
    {
        return $user->can((string) config('payroll.adjustments.approve_permission'))
            && $adjustment->approved_at === null
            && $adjustment->rejected_at === null
            && (string) $adjustment->proposed_by !== (string) $user->getAuthIdentifier();
    }

    public function reject(Authenticatable $user, PayrollAdjustment $adjustment): bool
    {
        return $this->approve($user, $adjustment);
    }
}

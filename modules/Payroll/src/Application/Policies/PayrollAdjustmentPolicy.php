<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Policies;

use Modules\Payroll\Domain\Models\PayrollAdjustment;

/**
 * سياسة التسويات.
 *
 * قرار العميل: صلاحيتان منفصلتان — propose للاقتراح و approve للاعتماد،
 * ومن يقترح لا يعتمد. لا فحص لأسماء الأدوار.
 */
final class PayrollAdjustmentPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('payroll.adjustments.view_any');
    }

    public function view($user, PayrollAdjustment $adjustment): bool
    {
        return $user->can('payroll.adjustments.view_any')
            || $user->can('payroll.adjustments.view')
            || (string) $adjustment->proposed_by === (string) $user->getAuthIdentifier();
    }

    public function create($user): bool
    {
        return $user->can((string) config('payroll.adjustments.propose_permission'));
    }

    /** التسوية المقترحة قيد معلّق لا يُعدَّل — تُرفض وتُقترح بديلة. */
    public function update($user, PayrollAdjustment $adjustment): bool
    {
        return false;
    }

    public function delete($user, PayrollAdjustment $adjustment): bool
    {
        return false;
    }

    public function approve($user, PayrollAdjustment $adjustment): bool
    {
        return $user->can((string) config('payroll.adjustments.approve_permission'))
            && $adjustment->approved_at === null
            && $adjustment->rejected_at === null
            && (string) $adjustment->proposed_by !== (string) $user->getAuthIdentifier();
    }

    public function reject($user, PayrollAdjustment $adjustment): bool
    {
        return $this->approve($user, $adjustment);
    }
}

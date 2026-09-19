<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Payroll\Domain\Models\PayrollAdjustment;

/**
 * سياسة التسويات.
 *
 * صلاحيتان منفصلتان — propose للاقتراح و approve للاعتماد. لا فحص لأسماء الأدوار.
 *
 * فصل «من يقترح لا يعتمد» سياسة مدرسة لا قاعدة ثابتة، فمصدرها
 * `config('payroll.adjustments.requires_different_approver')` كما يقرأه
 * `ApprovePayrollAdjustmentAction` تمامًا. كانت هذه السياسة تفرضه في الكود
 * بينما الإعداد يقول غير ذلك، فكان إطفاء الإعداد لا يغيّر شيئًا: الزر يختفي
 * ويبقى الرفض.
 */
final class PayrollAdjustmentPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('payroll.view');
    }

    public function view(Authenticatable $user, PayrollAdjustment $adjustment): bool
    {
        return (string) $adjustment->organization_id === (string) $user->getAttribute('organization_id')
            && $user->can('payroll.view');
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
        return (string) $adjustment->organization_id === (string) $user->getAttribute('organization_id')
            && $user->can((string) config('payroll.adjustments.approve_permission'))
            && $adjustment->approved_at === null
            && $adjustment->rejected_at === null
            && (
                config('payroll.adjustments.requires_different_approver') !== true
                || (string) $adjustment->proposed_by !== (string) $user->getAuthIdentifier()
            );
    }

    public function reject(Authenticatable $user, PayrollAdjustment $adjustment): bool
    {
        return $this->approve($user, $adjustment);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Policies;

use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollPeriod;

/**
 * سياسة فترات المستحقات.
 *
 * لا فحص لأسماء الأدوار — الصلاحيات عبر Gate وفق المصفوفة المعلنة.
 * الانتقالات بين الحالات تمرّ حصرًا بـ PayrollPeriodStatus::canTransitionTo.
 */
final class PayrollPeriodPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('payroll.periods.view_any');
    }

    public function view($user, PayrollPeriod $period): bool
    {
        return $user->can('payroll.periods.view_any')
            || $user->can('payroll.periods.view');
    }

    public function create($user): bool
    {
        return $user->can('payroll.periods.create');
    }

    public function update($user, PayrollPeriod $period): bool
    {
        return $user->can('payroll.periods.update')
            && ! $period->status->isFrozen();
    }

    /** لا حذف للفترات المالية أبدًا. */
    public function delete($user, PayrollPeriod $period): bool
    {
        return false;
    }

    public function calculate($user, PayrollPeriod $period): bool
    {
        return $user->can('payroll.calculate')
            && $period->status->canTransitionTo(\Modules\Payroll\Domain\Enums\PayrollPeriodStatus::Calculating);
    }

    public function review($user, PayrollPeriod $period): bool
    {
        return $user->can(config('payroll.period.review_permission'))
            && $period->status->canTransitionTo(\Modules\Payroll\Domain\Enums\PayrollPeriodStatus::UnderReview);
    }

    public function approve($user, PayrollPeriod $period): bool
    {
        return $user->can(config('payroll.period.approve_permission'))
            && $period->status->canTransitionTo(\Modules\Payroll\Domain\Enums\PayrollPeriodStatus::Approved);
    }

    public function pay($user, PayrollPeriod $period): bool
    {
        return $user->can(config('payroll.period.pay_permission'))
            && $period->status->canTransitionTo(\Modules\Payroll\Domain\Enums\PayrollPeriodStatus::Paid);
    }

    public function lock($user, PayrollPeriod $period): bool
    {
        return $user->can('payroll.lock')
            && $period->status->canTransitionTo(\Modules\Payroll\Domain\Enums\PayrollPeriodStatus::Locked);
    }
}

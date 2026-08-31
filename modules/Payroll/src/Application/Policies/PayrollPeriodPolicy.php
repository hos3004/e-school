<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
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
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('payroll.view');
    }

    public function view(Authenticatable $user, PayrollPeriod $period): bool
    {
        return $user->can('payroll.view')
            && (string) $period->organization_id === (string) $user->getAttribute('organization_id');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('payroll.calculate');
    }

    public function update(Authenticatable $user, PayrollPeriod $period): bool
    {
        return (string) $period->organization_id === (string) $user->getAttribute('organization_id')
            && $user->can('payroll.calculate')
            && !$period->status->isFrozen();
    }

    /** لا حذف للفترات المالية أبدًا. */
    public function delete(Authenticatable $user, PayrollPeriod $period): bool
    {
        return false;
    }

    public function calculate(Authenticatable $user, PayrollPeriod $period): bool
    {
        return (string) $period->organization_id === (string) $user->getAttribute('organization_id')
            && $user->can('payroll.calculate')
            && $period->status->canTransitionTo(PayrollPeriodStatus::Calculating);
    }

    public function review(Authenticatable $user, PayrollPeriod $period): bool
    {
        return (string) $period->organization_id === (string) $user->getAttribute('organization_id')
            && $user->can(config('payroll.period.review_permission'))
            && $period->status->canTransitionTo(PayrollPeriodStatus::UnderReview);
    }

    public function approve(Authenticatable $user, PayrollPeriod $period): bool
    {
        return (string) $period->organization_id === (string) $user->getAttribute('organization_id')
            && $user->can(config('payroll.period.approve_permission'))
            && $period->status->canTransitionTo(PayrollPeriodStatus::Approved);
    }

    public function pay(Authenticatable $user, PayrollPeriod $period): bool
    {
        return (string) $period->organization_id === (string) $user->getAttribute('organization_id')
            && $user->can(config('payroll.period.pay_permission'))
            && $period->status->canTransitionTo(PayrollPeriodStatus::Paid);
    }

    public function lock(Authenticatable $user, PayrollPeriod $period): bool
    {
        return (string) $period->organization_id === (string) $user->getAttribute('organization_id')
            && $user->can('payroll.lock')
            && $period->status->canTransitionTo(PayrollPeriodStatus::Locked);
    }
}

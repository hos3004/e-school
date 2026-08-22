<?php

declare(strict_types=1);

namespace Modules\Discipline\Application\Policies;

use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Models\ReactivationRequest;

/**
 * سياسة طلبات إعادة التفعيل.
 *
 * الطالب يرى طلباته ويقدّمها؛ مراجعتها وحسمها بصلاحية إدارية مستقلة
 * تأتي من config('discipline.reactivation.approver_permission').
 */
final class ReactivationRequestPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('discipline.view_any');
    }

    public function view($user, ReactivationRequest $request): bool
    {
        return $user->can('discipline.view_any')
            || (string) $user->getAuthIdentifier() === (string) $request->requested_by;
    }

    /** تقديم طلب جديد — بصلاحية مقدِّم الطلب أو صلاحية المُعتمِد. */
    public function create($user): bool
    {
        return $user->can('discipline.request_reactivation')
            || $this->approverPermission($user);
    }

    /** الطلبات غير قابلة للتعديل بعد التقديم. */
    public function update($user, ReactivationRequest $request): bool
    {
        return false;
    }

    public function delete($user, ReactivationRequest $request): bool
    {
        return false;
    }

    /** حسم الطلب (قبول/رفض) — بصلاحية المُعتمِد من الإعدادات وعلى طلب مفتوح. */
    public function decide($user, ReactivationRequest $request): bool
    {
        if (!$request->status->canTransitionTo(ReactivationStatus::Approved)
            && !$request->status->canTransitionTo(ReactivationStatus::Rejected)
        ) {
            return false;
        }

        return $this->approverPermission($user);
    }

    /** سحب الطلب من مقدِّمه قبل القرار. */
    public function cancel($user, ReactivationRequest $request): bool
    {
        return (string) $user->getAuthIdentifier() === (string) $request->requested_by
            && $request->status->canTransitionTo(ReactivationStatus::Cancelled);
    }

    private function approverPermission($user): bool
    {
        $permission = (string) config(
            'discipline.reactivation.approver_permission',
            'enrollment.reactivate',
        );

        return $permission !== '' && $user->can($permission);
    }
}

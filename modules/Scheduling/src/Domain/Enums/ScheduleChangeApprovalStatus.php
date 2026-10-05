<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Enums;

/** رد طالب واحد على طلب تغيير الموعد الدائم. */
enum ScheduleChangeApprovalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('scheduling::schedule_change.approval_'.$this->value);
    }
}

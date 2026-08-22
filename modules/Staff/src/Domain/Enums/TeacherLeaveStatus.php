<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Enums;

/**
 * دورة حياة إجازة المعلم.
 */
enum TeacherLeaveStatus: string
{
    /** طلب جديد بانتظار قرار الإدارة. */
    case Pending = 'pending';

    /** اعتُمدت — تمنع الجدولة عليها. */
    case Approved = 'approved';

    /** رُفضت. */
    case Rejected = 'rejected';

    /** سحبها المعلم قبل القرار. */
    case Cancelled = 'cancelled';

    /**
     * الانتقالات المسموحة. أي انتقال آخر مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [
                self::Approved,
                self::Rejected,
                self::Cancelled,
            ],
            self::Approved,
            self::Rejected,
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return __('staff::enums.leave_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'gray',
        };
    }
}

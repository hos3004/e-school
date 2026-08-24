<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Enums;

enum TeacherAvailabilityApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved],
            self::Approved => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}

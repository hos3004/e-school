<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Enums;

enum RegistrationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case WaitingAssignment = 'waiting_assignment';
    case Assigned = 'assigned';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::UnderReview, self::Accepted, self::Rejected],
            self::UnderReview => [self::Accepted, self::Rejected],
            self::Accepted => [self::WaitingAssignment],
            self::WaitingAssignment => [self::Assigned],
            self::Rejected, self::Assigned => [],
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

    public function isClearedForAssignment(): bool
    {
        return in_array($this, [self::WaitingAssignment, self::Assigned], true);
    }

    public function label(): string
    {
        return __('students::registration.status.'.$this->value);
    }
}

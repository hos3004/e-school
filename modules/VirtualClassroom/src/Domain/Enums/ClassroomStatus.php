<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Enums;

/** دورة حياة الفصل المحلي المستقلة عن أسماء حالات المزوّد. */
enum ClassroomStatus: string
{
    case Pending = 'pending';
    case Provisioned = 'provisioned';
    case Running = 'running';
    case Ended = 'ended';
    case Failed = 'failed';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Provisioned, self::Failed],
            self::Provisioned => [self::Running, self::Ended, self::Failed],
            self::Running => [self::Ended, self::Failed],
            self::Failed => [self::Pending, self::Provisioned],
            self::Ended => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function canProvision(): bool
    {
        return in_array($this, [self::Pending, self::Failed], true);
    }

    public function label(): string
    {
        return __('virtualclassroom::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Provisioned => 'info',
            self::Running => 'success',
            self::Ended => 'gray',
            self::Failed => 'danger',
        };
    }
}

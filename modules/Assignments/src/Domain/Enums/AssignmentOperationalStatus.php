<?php

declare(strict_types=1);

namespace Modules\Assignments\Domain\Enums;

enum AssignmentOperationalStatus: string
{
    case Scheduled = 'scheduled';
    case Open = 'open';
    case LateWindow = 'late_window';
    case Closed = 'closed';

    public function label(): string
    {
        return __('assignments::status.assignment.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Open => 'success',
            self::LateWindow => 'warning',
            self::Closed => 'gray',
        };
    }
}

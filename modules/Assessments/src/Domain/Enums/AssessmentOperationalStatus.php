<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Enums;

enum AssessmentOperationalStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __('assessments::status.assessment.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Open => 'success',
            self::Closed => 'danger',
        };
    }
}

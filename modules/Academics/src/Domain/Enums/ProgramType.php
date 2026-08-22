<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Enums;

enum ProgramType: string
{
    case FixedDuration = 'fixed_duration';
    case Ongoing = 'ongoing';

    public function label(): string
    {
        return __('academics::enums.program_type.'.$this->value);
    }
}

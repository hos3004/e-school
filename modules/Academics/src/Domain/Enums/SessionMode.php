<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Enums;

enum SessionMode: string
{
    case Individual = 'individual';
    case Group = 'group';
    case Both = 'both';

    public function label(): string
    {
        return __('academics::enums.session_mode.'.$this->value);
    }
}

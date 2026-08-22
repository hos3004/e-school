<?php

declare(strict_types=1);

namespace Modules\Academics\Domain\Enums;

enum TargetGender: string
{
    case Male = 'male';
    case Female = 'female';
    case All = 'all';

    public function label(): string
    {
        return __('academics::enums.target_gender.'.$this->value);
    }
}

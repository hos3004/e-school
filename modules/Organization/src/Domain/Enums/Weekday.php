<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Enums;

/**
 * أيام الأسبوع — تُستخدم لتحديد أول يوم في أسبوع المؤسسة.
 */
enum Weekday: string
{
    case Saturday = 'saturday';
    case Sunday = 'sunday';
    case Monday = 'monday';

    public function label(): string
    {
        return __('organization::enums.weekday.'.$this->value);
    }
}

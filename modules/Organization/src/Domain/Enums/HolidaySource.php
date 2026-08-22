<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Enums;

/**
 * مصدر إدخال العطلة في التقويم.
 */
enum HolidaySource: string
{
    /** أُضيفت يدويًا من إدارة المؤسسة. */
    case Manual = 'manual';

    /** جُلبت آليًا من تقويم رسمي خارجي. */
    case Imported = 'imported';

    public function label(): string
    {
        return __('organization::enums.holiday_source.'.$this->value);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';

    case PartTime = 'part_time';

    case Hourly = 'hourly';

    case Contractor = 'contractor';

    /**
     * @return list<self>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): self => $case,
            self::cases(),
        );
    }

    public function label(): string
    {
        return __('staff::enums.employment_type.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::FullTime => 'green',
            self::PartTime => 'blue',
            self::Hourly => 'amber',
            self::Contractor => 'violet',
        };
    }
}

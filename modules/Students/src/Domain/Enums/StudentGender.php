<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Enums;

/**
 * جنس الطالب — قيمة وصفية ثابتة لا تدخل في آلة حالات.
 */
enum StudentGender: string
{
    case Male = 'male';

    case Female = 'female';

    /** @return list<self> */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): self => $case,
            self::cases(),
        );
    }

    public function label(): string
    {
        return __('students::gender.'.$this->value);
    }
}

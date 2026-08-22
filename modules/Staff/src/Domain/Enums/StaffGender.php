<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Enums;

/**
 * يحتفظ Staff بقيم الجنس محليًا حتى لا يعتمد على موديول Students من الطبقة نفسها.
 */
enum StaffGender: string
{
    case Male = 'male';

    case Female = 'female';

    /** @return list<self> */
    public static function values(): array
    {
        return self::cases();
    }
}

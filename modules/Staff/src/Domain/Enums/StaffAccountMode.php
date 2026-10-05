<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Enums;

enum StaffAccountMode: string
{
    case NewAccount = 'new';
    case ExistingAccount = 'existing';
}

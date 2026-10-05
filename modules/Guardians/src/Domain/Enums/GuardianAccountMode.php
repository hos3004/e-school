<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Enums;

enum GuardianAccountMode: string
{
    case NewAccount = 'new';
    case ExistingAccount = 'existing';
}

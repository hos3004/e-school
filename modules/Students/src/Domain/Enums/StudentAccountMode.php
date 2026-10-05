<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Enums;

enum StudentAccountMode: string
{
    case NewAccount = 'new';
    case ExistingAccount = 'existing';
}

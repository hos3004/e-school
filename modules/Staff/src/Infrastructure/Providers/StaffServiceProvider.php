<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class StaffServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Staff';
    }
}

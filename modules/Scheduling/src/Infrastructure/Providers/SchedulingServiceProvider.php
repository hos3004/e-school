<?php

declare(strict_types=1);

namespace Modules\Scheduling\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class SchedulingServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Scheduling';
    }
}

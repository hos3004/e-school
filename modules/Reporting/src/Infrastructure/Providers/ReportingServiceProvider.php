<?php

declare(strict_types=1);

namespace Modules\Reporting\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class ReportingServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Reporting';
    }
}

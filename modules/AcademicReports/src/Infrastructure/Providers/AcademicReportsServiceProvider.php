<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class AcademicReportsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'AcademicReports';
    }
}

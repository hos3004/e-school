<?php

declare(strict_types=1);

namespace Modules\Assignments\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class AssignmentsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Assignments';
    }
}

<?php

declare(strict_types=1);

namespace Modules\Assessments\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class AssessmentsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Assessments';
    }
}

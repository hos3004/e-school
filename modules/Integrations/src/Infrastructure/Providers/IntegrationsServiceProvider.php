<?php

declare(strict_types=1);

namespace Modules\Integrations\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class IntegrationsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Integrations';
    }
}

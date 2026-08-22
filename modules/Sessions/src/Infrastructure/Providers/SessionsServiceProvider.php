<?php

declare(strict_types=1);

namespace Modules\Sessions\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class SessionsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Sessions';
    }
}

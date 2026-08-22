<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class NotificationsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Notifications';
    }
}

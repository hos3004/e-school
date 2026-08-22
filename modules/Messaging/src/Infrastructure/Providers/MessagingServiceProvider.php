<?php

declare(strict_types=1);

namespace Modules\Messaging\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class MessagingServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Messaging';
    }
}

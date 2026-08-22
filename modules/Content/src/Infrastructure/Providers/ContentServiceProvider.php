<?php

declare(strict_types=1);

namespace Modules\Content\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class ContentServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Content';
    }
}

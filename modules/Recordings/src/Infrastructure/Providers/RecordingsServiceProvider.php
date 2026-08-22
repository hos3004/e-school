<?php

declare(strict_types=1);

namespace Modules\Recordings\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class RecordingsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Recordings';
    }
}

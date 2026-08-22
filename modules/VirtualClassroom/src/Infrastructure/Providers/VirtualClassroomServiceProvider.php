<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class VirtualClassroomServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'VirtualClassroom';
    }
}

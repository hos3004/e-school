<?php

declare(strict_types=1);

namespace Modules\Certificates\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class CertificatesServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Certificates';
    }
}

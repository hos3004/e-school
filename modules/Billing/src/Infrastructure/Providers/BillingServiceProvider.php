<?php

declare(strict_types=1);

namespace Modules\Billing\Infrastructure\Providers;

use Shared\Module\BaseModuleServiceProvider;

final class BillingServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Billing';
    }
}

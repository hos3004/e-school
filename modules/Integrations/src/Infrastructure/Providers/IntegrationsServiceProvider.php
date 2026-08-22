<?php

declare(strict_types=1);

namespace Modules\Integrations\Infrastructure\Providers;

use Modules\Integrations\Application\Listeners\FlagConnectionOnError;
use Modules\Integrations\Application\Policies\IntegrationConnectionPolicy;
use Modules\Integrations\Application\Policies\IntegrationProviderPolicy;
use Modules\Integrations\Application\Policies\IntegrationWebhookDeliveryPolicy;
use Modules\Integrations\Domain\Events\WebhookDeadLettered;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Shared\Module\BaseModuleServiceProvider;
use Shared\Support\DatabaseTransaction;
use Shared\Support\Transaction;

final class IntegrationsServiceProvider extends BaseModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Integrations';
    }

    /**
     * @return array<class-string, list<class-string>>
     */
    protected function listeners(): array
    {
        return [
            WebhookDeadLettered::class => [
                FlagConnectionOnError::class,
            ],
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            IntegrationProvider::class => IntegrationProviderPolicy::class,
            IntegrationConnection::class => IntegrationConnectionPolicy::class,
            IntegrationWebhookDelivery::class => IntegrationWebhookDeliveryPolicy::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function bindings(): array
    {
        return [
            Transaction::class => DatabaseTransaction::class,
        ];
    }
}

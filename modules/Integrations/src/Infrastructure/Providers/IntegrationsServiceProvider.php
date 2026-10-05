<?php

declare(strict_types=1);

namespace Modules\Integrations\Infrastructure\Providers;

use InvalidArgumentException;
use Modules\Integrations\Application\Listeners\FlagConnectionOnError;
use Modules\Integrations\Application\Policies\IntegrationConnectionPolicy;
use Modules\Integrations\Application\Policies\IntegrationProviderPolicy;
use Modules\Integrations\Application\Policies\IntegrationWebhookDeliveryPolicy;
use Modules\Integrations\Application\Services\GreenApiConnectionManager;
use Modules\Integrations\Application\Services\LlmConnectionManager;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Integrations\Domain\Contracts\LlmConnections;
use Modules\Integrations\Domain\Contracts\LlmGateway;
use Modules\Integrations\Domain\Contracts\WhatsAppDirectSender;
use Modules\Integrations\Domain\Events\WebhookDeadLettered;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Modules\Integrations\Infrastructure\Gateways\AnthropicGateway;
use Modules\Integrations\Infrastructure\Gateways\GreenApiDirectSender;
use Modules\Integrations\Infrastructure\Gateways\NullLlmGateway;
use Shared\Module\BaseModuleServiceProvider;
use Shared\Support\DatabaseTransaction;
use Shared\Support\Transaction;

final class IntegrationsServiceProvider extends BaseModuleServiceProvider
{
    /**
     * المشغّلات المعروفة للنموذج اللغوي. الاسم المجهول يرفع استثناءً عند أول
     * استعمال ولا يسقط صامتًا إلى المشغّل الوهمي: السقوط الصامت كان سيجعل خطأً
     * مطبعيًا في الإعداد يمر كأنه بوت يعمل، وهو يردّ ردودًا معلّبة.
     *
     * @var array<string, class-string<LlmGateway>>
     */
    private const array LLM_DRIVERS = [
        'anthropic' => AnthropicGateway::class,
        'null' => NullLlmGateway::class,
    ];

    protected function moduleName(): string
    {
        return 'Integrations';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(LlmGateway::class, static function ($app): LlmGateway {
            $driver = (string) config('llm.driver', 'null');
            $implementation = self::LLM_DRIVERS[$driver] ?? null;

            if ($implementation === null) {
                throw new InvalidArgumentException(
                    sprintf('Unknown llm.driver [%s].', $driver),
                );
            }

            return $app->make($implementation);
        });
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
            GreenApiConnections::class => GreenApiConnectionManager::class,
            WhatsAppDirectSender::class => GreenApiDirectSender::class,
            LlmConnections::class => LlmConnectionManager::class,
        ];
    }
}

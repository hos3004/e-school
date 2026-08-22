<?php

declare(strict_types=1);

namespace Modules\Integrations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<IntegrationConnection>
 */
final class IntegrationConnectionFactory extends Factory
{
    protected $model = IntegrationConnection::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'provider_id' => IntegrationProvider::factory(),
            'status' => ConnectionStatus::Pending,
            'credentials' => null,
            'settings' => null,
        ];
    }

    public function withStatus(ConnectionStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Integrations\Database\Factories\IntegrationConnectionFactory;
use Modules\Integrations\Database\Factories\IntegrationProviderFactory;
use Modules\Integrations\Database\Factories\IntegrationWebhookDeliveryFactory;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Tests\Support\ApiUser;
use Shared\Testing\Fixtures;

function integrationsApiUser(): ApiUser
{
    return new ApiUser('01INTUSER000000000000000000', Fixtures::organizationId());
}

it('stores a connection over the api and returns the pending status', function (): void {
    /** @var \Tests\TestCase $this */
    Event::fake();
    Gate::after(fn (): bool => true);

    $provider = IntegrationProviderFactory::new()->create();

    $response = $this->actingAs(integrationsApiUser())
        ->postJson('/api/integrations/connections', [
            'organization_id' => Fixtures::organizationId(),
            'provider_id' => (string) $provider->getKey(),
            'credentials' => ['token' => 'abc'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status.value', 'pending')
        ->assertJsonPath('data.has_credentials', true);

    expect(IntegrationConnection::query()->where('provider_id', (string) $provider->getKey())->exists())->toBeTrue();
});

it('activates a connection over the api', function (): void {
    /** @var \Tests\TestCase $this */
    Event::fake();
    Gate::after(fn (): bool => true);

    $connection = IntegrationConnectionFactory::new()->create([
        'organization_id' => Fixtures::organizationId(),
        'status' => ConnectionStatus::Pending,
    ]);

    $this->actingAs(integrationsApiUser())
        ->postJson("/api/integrations/connections/{$connection->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status.value', 'active');
});

it('requires a documented reason to disable a connection', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::after(fn (): bool => true);

    $connection = IntegrationConnectionFactory::new()->create([
        'organization_id' => Fixtures::organizationId(),
        'status' => ConnectionStatus::Active,
    ]);

    $this->actingAs(integrationsApiUser())
        ->postJson("/api/integrations/connections/{$connection->id}/disable", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('settles a delivery over the api as delivered', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::after(fn (): bool => true);

    $connection = IntegrationConnectionFactory::new()->create([
        'organization_id' => Fixtures::organizationId(),
        'status' => ConnectionStatus::Active,
    ]);
    $delivery = IntegrationWebhookDeliveryFactory::new()->create([
        'connection_id' => (string) $connection->getKey(),
    ]);

    $this->actingAs(integrationsApiUser())
        ->postJson("/api/integrations/deliveries/{$delivery->id}/settle", [
            'success' => true,
            'response_code' => 204,
        ])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'delivered');

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered);
});

it('requeues a dead delivery over the api', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::after(fn (): bool => true);

    $connection = IntegrationConnectionFactory::new()->create([
        'organization_id' => Fixtures::organizationId(),
        'status' => ConnectionStatus::Active,
    ]);
    $delivery = IntegrationWebhookDeliveryFactory::new()->create([
        'connection_id' => (string) $connection->getKey(),
        'status' => DeliveryStatus::Dead,
    ]);

    $this->actingAs(integrationsApiUser())
        ->postJson("/api/integrations/deliveries/{$delivery->id}/requeue", [
            'reason' => 'manual replay after provider outage',
        ])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'retrying');
});

it('forbids storing connections without the create ability', function (): void {
    /** @var \Tests\TestCase $this */
    $provider = IntegrationProviderFactory::new()->create();

    $this->actingAs(integrationsApiUser())
        ->postJson('/api/integrations/connections', [
            'organization_id' => Fixtures::organizationId(),
            'provider_id' => (string) $provider->getKey(),
        ])
        ->assertForbidden();

    expect(IntegrationConnection::query()->count())->toBe(0);
});

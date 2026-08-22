<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Integrations\Application\Actions\EstablishConnectionAction;
use Modules\Integrations\Database\Factories\IntegrationConnectionFactory;
use Modules\Integrations\Database\Factories\IntegrationProviderFactory;
use Modules\Integrations\Domain\Events\ConnectionEstablished;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

it('establishes a pending connection and publishes ConnectionEstablished', function (): void {
    Event::fake([ConnectionEstablished::class]);

    $provider = IntegrationProviderFactory::new()->create();

    $connection = app(EstablishConnectionAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        providerId: (string) $provider->getKey(),
        credentials: ['api_key' => 'secret'],
    );

    expect($connection->exists)->toBeTrue()
        ->and($connection->status->value)->toBe('pending')
        ->and($connection->credentials)->toBe(['api_key' => 'secret']);

    Event::assertDispatched(
        ConnectionEstablished::class,
        fn (ConnectionEstablished $event): bool => $event->payload()['provider_id'] === (string) $provider->getKey()
            && $event->payload()['organization_id'] === (string) $connection->organization_id,
    );
});

it('refuses an unknown provider', function (): void {
    app(EstablishConnectionAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        providerId: (string) Str::ulid(),
    );
})->throws(BusinessRuleViolation::class);

it('refuses an inactive provider', function (): void {
    $provider = IntegrationProviderFactory::new()->inactive()->create();

    app(EstablishConnectionAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        providerId: (string) $provider->getKey(),
    );
})->throws(BusinessRuleViolation::class);

it('enforces the configured per-provider connection limit', function (): void {
    config()->set('integrations.connections.max_per_provider', 1);

    $organizationId = Fixtures::organizationId();
    $existing = IntegrationConnectionFactory::new()->create([
        'organization_id' => $organizationId,
    ]);

    app(EstablishConnectionAction::class)->execute(
        organizationId: $organizationId,
        providerId: (string) $existing->provider_id,
    );

    expect(IntegrationConnection::query()->count())->toBe(1);
})->throws(BusinessRuleViolation::class);

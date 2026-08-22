<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Integrations\Application\Actions\ActivateConnectionAction;
use Modules\Integrations\Application\Actions\DisableConnectionAction;
use Modules\Integrations\Database\Factories\IntegrationConnectionFactory;
use Modules\Integrations\Database\Factories\IntegrationProviderFactory;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Events\ConnectionActivated;
use Modules\Integrations\Domain\Events\ConnectionDisabled;
use Shared\Support\BusinessRuleViolation;

it('activates a pending connection whose provider is active', function (): void {
    Event::fake([ConnectionActivated::class]);

    $provider = IntegrationProviderFactory::new()->create();
    $connection = IntegrationConnectionFactory::new()->create([
        'provider_id' => (string) $provider->getKey(),
        'status' => ConnectionStatus::Pending,
    ]);

    $activated = app(ActivateConnectionAction::class)->execute($connection);

    expect($activated->status)->toBe(ConnectionStatus::Active)
        ->and($activated->activated_at)->not->toBeNull();

    Event::assertDispatched(ConnectionActivated::class);
});

it('refuses activation when the provider itself is inactive', function (): void {
    $provider = IntegrationProviderFactory::new()->inactive()->create();
    $connection = IntegrationConnectionFactory::new()->create([
        'provider_id' => (string) $provider->getKey(),
        'status' => ConnectionStatus::Pending,
    ]);

    app(ActivateConnectionAction::class)->execute($connection);
})->throws(BusinessRuleViolation::class);

it('refuses an illegal status transition through the state machine', function (): void {
    $provider = IntegrationProviderFactory::new()->create();
    $connection = IntegrationConnectionFactory::new()->create([
        'provider_id' => (string) $provider->getKey(),
        'status' => ConnectionStatus::Expired,
    ]);

    app(ActivateConnectionAction::class)->execute($connection);
})->throws(BusinessRuleViolation::class);

it('disables an active connection with a documented reason', function (): void {
    Event::fake([ConnectionDisabled::class]);

    $connection = IntegrationConnectionFactory::new()
        ->withStatus(ConnectionStatus::Active)
        ->create();

    $disabled = app(DisableConnectionAction::class)->execute($connection, 'rotation');

    expect($disabled->status)->toBe(ConnectionStatus::Disabled)
        ->and($disabled->disabled_at)->not->toBeNull();

    Event::assertDispatched(
        ConnectionDisabled::class,
        fn (ConnectionDisabled $event): bool => $event->payload()['reason'] === 'rotation',
    );
});

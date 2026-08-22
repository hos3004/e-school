<?php

declare(strict_types=1);

use Modules\Integrations\Domain\Enums\ConnectionStatus;

it('allows pending to activate or disable only', function (): void {
    expect(ConnectionStatus::Pending->canTransitionTo(ConnectionStatus::Active))->toBeTrue()
        ->and(ConnectionStatus::Pending->canTransitionTo(ConnectionStatus::Disabled))->toBeTrue()
        ->and(ConnectionStatus::Pending->canTransitionTo(ConnectionStatus::Error))->toBeFalse()
        ->and(ConnectionStatus::Pending->canTransitionTo(ConnectionStatus::Expired))->toBeFalse();
});

it('never leaves the expired terminal state', function (): void {
    expect(ConnectionStatus::Expired->allowedTransitions())->toBe([])
        ->and(ConnectionStatus::Expired->canTransitionTo(ConnectionStatus::Active))->toBeFalse()
        ->and(ConnectionStatus::Expired->canTransitionTo(ConnectionStatus::Pending))->toBeFalse();
});

it('accepts deliveries only while active', function (): void {
    expect(ConnectionStatus::Active->acceptsDeliveries())->toBeTrue();

    foreach (ConnectionStatus::cases() as $status) {
        if ($status === ConnectionStatus::Active) {
            continue;
        }

        expect($status->acceptsDeliveries())->toBeFalse();
    }
});

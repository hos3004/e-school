<?php

declare(strict_types=1);

use Modules\Integrations\Domain\Enums\DeliveryStatus;

it('marks delivered as the only successful terminal state', function (): void {
    expect(DeliveryStatus::Delivered->isTerminal())->toBeTrue()
        ->and(DeliveryStatus::Delivered->allowedTransitions())->toBe([]);
});

it('lets a dead delivery be requeued for a manual retry', function (): void {
    expect(DeliveryStatus::Dead->canTransitionTo(DeliveryStatus::Retrying))->toBeTrue()
        ->and(DeliveryStatus::Dead->canTransitionTo(DeliveryStatus::Pending))->toBeFalse()
        ->and(DeliveryStatus::Dead->isTerminal())->toBeTrue();
});

it('rejects transitions outside the documented machine', function (): void {
    expect(DeliveryStatus::Failed->canTransitionTo(DeliveryStatus::Delivered))->toBeFalse()
        ->and(DeliveryStatus::Pending->canTransitionTo(DeliveryStatus::Pending))->toBeFalse()
        ->and(DeliveryStatus::Retrying->canTransitionTo(DeliveryStatus::Retrying))->toBeFalse();
});

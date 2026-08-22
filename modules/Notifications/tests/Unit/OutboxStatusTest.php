<?php

declare(strict_types=1);

use Modules\Notifications\Domain\Enums\OutboxStatus;

it('allows queued to move to sending or cancellation only', function (): void {
    expect(OutboxStatus::Queued->canTransitionTo(OutboxStatus::Sending))->toBeTrue()
        ->and(OutboxStatus::Queued->canTransitionTo(OutboxStatus::Cancelled))->toBeTrue()
        ->and(OutboxStatus::Queued->canTransitionTo(OutboxStatus::Sent))->toBeFalse()
        ->and(OutboxStatus::Queued->canTransitionTo(OutboxStatus::Failed))->toBeFalse();
});

it('allows sending to settle into sent, failed or back to queued', function (): void {
    expect(OutboxStatus::Sending->canTransitionTo(OutboxStatus::Sent))->toBeTrue()
        ->and(OutboxStatus::Sending->canTransitionTo(OutboxStatus::Failed))->toBeTrue()
        ->and(OutboxStatus::Sending->canTransitionTo(OutboxStatus::Queued))->toBeTrue()
        ->and(OutboxStatus::Sending->canTransitionTo(OutboxStatus::Cancelled))->toBeFalse();
});

it('lets failed notifications be requeued only', function (): void {
    expect(OutboxStatus::Failed->canTransitionTo(OutboxStatus::Queued))->toBeTrue()
        ->and(OutboxStatus::Failed->canTransitionTo(OutboxStatus::Sent))->toBeFalse()
        ->and(OutboxStatus::Failed->isDeliverable())->toBeFalse();
});

it('treats sent and cancelled as terminal', function (): void {
    expect(OutboxStatus::Sent->isTerminal())->toBeTrue()
        ->and(OutboxStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(OutboxStatus::Sent->allowedTransitions())->toBe([])
        ->and(OutboxStatus::Cancelled->allowedTransitions())->toBe([])
        ->and(OutboxStatus::Sent->isDeliverable())->toBeFalse();
});

it('exposes translated labels and colors', function (): void {
    foreach (OutboxStatus::cases() as $status) {
        expect($status->label())->not->toBeEmpty()
            ->and($status->color())->not->toBeEmpty();
    }
});

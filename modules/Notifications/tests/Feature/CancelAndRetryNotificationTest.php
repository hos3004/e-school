<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Notifications\Application\Actions\CancelNotificationAction;
use Modules\Notifications\Application\Actions\RetryNotificationAction;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationCancelled;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use PHPUnit\Framework\Assert;
use Shared\Support\BusinessRuleViolation;

it('cancels a queued notification with an auditable reason', function (): void {
    Event::fake([NotificationCancelled::class]);

    $outbox = NotificationOutbox::factory()->create();

    $cancelled = app(CancelNotificationAction::class)->execute($outbox, 'duplicate_event');

    expect($cancelled->status)->toBe(OutboxStatus::Cancelled);

    Event::assertDispatched(
        NotificationCancelled::class,
        fn (NotificationCancelled $event): bool => $event->reason === 'duplicate_event'
            && $event->outboxId === $outbox->id,
    );
});

it('refuses to cancel notifications that left the queue', function (): void {
    foreach ([OutboxStatus::Sent, OutboxStatus::Failed, OutboxStatus::Sending, OutboxStatus::Cancelled] as $status) {
        $outbox = NotificationOutbox::factory()->withStatus($status)->create();

        try {
            app(CancelNotificationAction::class)->execute($outbox, 'late_cancel');
            Assert::fail('Expected BusinessRuleViolation was not thrown.');
        } catch (BusinessRuleViolation $violation) {
            expect($violation->rule)->toBe('notifications.not_cancellable');
        }
    }
});

it('requeues a failed notification on manual retry', function (): void {
    $failed = NotificationOutbox::factory()->failed()->create();

    expect($failed->status)->toBe(OutboxStatus::Failed)
        ->and($failed->attempts)->toBe((int) config('notifications.delivery.max_retries') + 1);

    $retried = app(RetryNotificationAction::class)->execute($failed);

    expect($retried->refresh()->status)->toBe(OutboxStatus::Queued)
        ->and($retried->attempts)->toBe(0)
        ->and($retried->last_error)->toBeNull()
        ->and($retried->last_error_retryable)->toBeNull();
});

it('does not overwrite a newer status when retrying a stale failed model', function (): void {
    $stale = NotificationOutbox::factory()->failed()->create();

    DB::table('notification_outbox')->where('id', $stale->id)->update([
        'status' => OutboxStatus::Sent->value,
        'sent_at' => now('UTC'),
    ]);

    try {
        app(RetryNotificationAction::class)->execute($stale);
        Assert::fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('notifications.not_retryable');
    }

    expect($stale->refresh()->status)->toBe(OutboxStatus::Sent);
});

it('refuses to retry anything but failed notifications', function (): void {
    foreach ([OutboxStatus::Queued, OutboxStatus::Sent, OutboxStatus::Cancelled] as $status) {
        $outbox = NotificationOutbox::factory()->withStatus($status)->create();

        try {
            app(RetryNotificationAction::class)->execute($outbox);
            Assert::fail('Expected BusinessRuleViolation was not thrown.');
        } catch (BusinessRuleViolation $violation) {
            expect($violation->rule)->toBe('notifications.not_retryable');
        }
    }
});

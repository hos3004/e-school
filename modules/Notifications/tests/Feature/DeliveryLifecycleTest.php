<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Modules\Notifications\Application\Actions\MarkNotificationSendingAction;
use Modules\Notifications\Application\Actions\RecordDeliveryAttemptAction;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationFailed;
use Modules\Notifications\Domain\Events\NotificationSent;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

afterEach(function (): void {
    /** @var TestCase $this */
    CarbonImmutable::setTestNow();
});

it('claims queued notifications for exactly one sender', function (): void {
    /** @var TestCase $this */
    $outbox = NotificationOutbox::factory()->state([
        'scheduled_for' => now('UTC'),
    ])->create();

    $claimed = app(MarkNotificationSendingAction::class)->execute($outbox);

    expect($claimed->refresh()->status)->toBe(OutboxStatus::Sending);

    try {
        app(MarkNotificationSendingAction::class)->execute($claimed->refresh());
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('notifications.not_dispatchable');
    }
});

it('closes the notification as sent after a successful attempt', function (): void {
    /** @var TestCase $this */
    Event::fake([NotificationSent::class]);

    $outbox = NotificationOutbox::factory()->withStatus(OutboxStatus::Sending)->create();

    $attempt = app(RecordDeliveryAttemptAction::class)->execute($outbox, succeeded: true);

    expect($attempt->succeeded)->toBeTrue()
        ->and($attempt->attempt_number)->toBe(1)
        ->and($outbox->refresh()->status)->toBe(OutboxStatus::Sent)
        ->and($outbox->sent_at)->not->toBeNull()
        ->and(NotificationDeliveryAttempt::query()->count())->toBe(1);

    Event::assertDispatched(NotificationSent::class);
});

it('requeues failed attempts while retries remain', function (): void {
    /** @var TestCase $this */
    config(['notifications.delivery.max_retries' => 2]);

    $outbox = NotificationOutbox::factory()->withStatus(OutboxStatus::Sending)->create();

    $action = app(RecordDeliveryAttemptAction::class);

    $attempt = $action->execute($outbox, succeeded: false, error: 'timeout');

    expect($outbox->refresh()->status)->toBe(OutboxStatus::Queued)
        ->and($attempt->error)->toBe('timeout')
        ->and($outbox->attempts)->toBe(1);

    CarbonImmutable::setTestNow(CarbonImmutable::instance($outbox->scheduled_for));
    $sending = app(MarkNotificationSendingAction::class)->execute($outbox->refresh());
    $second = $action->execute($sending, succeeded: true);

    expect($second->attempt_number)->toBe(2)
        ->and($outbox->refresh()->status)->toBe(OutboxStatus::Sent);
});

it('declares final failure once the configured maximum is exhausted', function (): void {
    /** @var TestCase $this */
    Event::fake([NotificationFailed::class]);

    config(['notifications.delivery.max_retries' => 1]);

    $outbox = NotificationOutbox::factory()->withStatus(OutboxStatus::Sending)->create();
    $action = app(RecordDeliveryAttemptAction::class);

    $first = $action->execute($outbox, succeeded: false);
    expect($outbox->refresh()->status)->toBe(OutboxStatus::Queued)
        ->and($first->attempt_number)->toBe(1);

    CarbonImmutable::setTestNow(CarbonImmutable::instance($outbox->scheduled_for));
    $sending = app(MarkNotificationSendingAction::class)->execute($outbox->refresh());
    $second = $action->execute($sending, succeeded: false, error: 'smtp_down');

    expect($second->attempt_number)->toBe(2)
        ->and($outbox->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and(NotificationDeliveryAttempt::query()->count())->toBe(2);

    Event::assertDispatched(NotificationFailed::class);
});

it('refuses to record attempts on terminal notifications', function (): void {
    /** @var TestCase $this */
    $sent = NotificationOutbox::factory()->withStatus(OutboxStatus::Sent)->create();
    $cancelled = NotificationOutbox::factory()->withStatus(OutboxStatus::Cancelled)->create();

    foreach ([$sent, $cancelled] as $outbox) {
        try {
            app(RecordDeliveryAttemptAction::class)->execute($outbox, succeeded: false);
            $this->fail('Expected BusinessRuleViolation was not thrown.');
        } catch (BusinessRuleViolation $violation) {
            expect($violation->rule)->toBe('notifications.attempt_not_recordable');
        }
    }
});

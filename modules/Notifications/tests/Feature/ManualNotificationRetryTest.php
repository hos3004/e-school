<?php

declare(strict_types=1);

use Modules\Notifications\Application\Actions\RetryNotificationAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

it('records the administrator and queues a genuinely new delivery attempt on manual resend', function (): void {
    $actorId = Fixtures::userId();
    $failed = NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->failed()
        ->state(['last_error_retryable' => false])
        ->create();

    NotificationDeliveryAttempt::factory()->create([
        'organization_id' => $failed->organization_id,
        'outbox_id' => $failed->id,
        'attempt_number' => 1,
        'succeeded' => false,
        'retryable' => false,
    ]);

    app(RetryNotificationAction::class)->executeManually($failed, $actorId);

    $failed->refresh();

    expect($failed->status)->toBe(OutboxStatus::Sent)
        ->and($failed->last_manual_retry_by)->toBe($actorId)
        ->and($failed->last_manual_retry_at)->not->toBeNull()
        ->and(NotificationDeliveryAttempt::query()->where('outbox_id', $failed->id)->count())->toBe(2)
        ->and(NotificationDeliveryAttempt::query()->where('outbox_id', $failed->id)->max('attempt_number'))->toBe(2);
});

it('does not automatically reopen a permanent failure', function (): void {
    $failed = NotificationOutbox::factory()
        ->failed()
        ->state(['last_error_retryable' => false])
        ->create();

    try {
        app(RetryNotificationAction::class)->execute($failed);
        test()->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('notifications.failure_not_retryable');
    }

    expect($failed->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and($failed->last_manual_retry_by)->toBeNull()
        ->and($failed->last_manual_retry_at)->toBeNull();
});

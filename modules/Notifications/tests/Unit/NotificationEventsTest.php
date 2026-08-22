<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationCancelled;
use Modules\Notifications\Domain\Events\NotificationFailed;
use Modules\Notifications\Domain\Events\NotificationQueued;
use Modules\Notifications\Domain\Events\NotificationSent;

it('describes queueing with identifiers and primitives only', function (): void {
    $scheduledFor = CarbonImmutable::now('UTC');

    $event = new NotificationQueued(
        outboxId: '01OUTBOX0000000000000000',
        organizationId: '01ORG00000000000000000000',
        userId: '01USER000000000000000000',
        category: 'scheduling',
        channel: 'email',
        locale: 'ar',
        eventName: 'sessions.cancelled',
        eventId: '01EVENT00000000000000000',
        idempotencyKey: '01EVENT00000000000000000:scheduling:email',
        scheduledFor: $scheduledFor,
    );

    expect($event->name())->toBe('notifications.queued')
        ->and($event->module())->toBe('notifications')
        ->and($event->payload())->toBe([
            'outbox_id' => '01OUTBOX0000000000000000',
            'organization_id' => '01ORG00000000000000000000',
            'user_id' => '01USER000000000000000000',
            'category' => 'scheduling',
            'channel' => 'email',
            'locale' => 'ar',
            'event_name' => 'sessions.cancelled',
            'event_id' => '01EVENT00000000000000000',
            'idempotency_key' => '01EVENT00000000000000000:scheduling:email',
            'scheduled_for' => $scheduledFor->toIso8601String(),
        ]);
});

it('describes successful delivery in the past tense', function (): void {
    $sentAt = CarbonImmutable::now('UTC');

    $event = new NotificationSent(
        outboxId: '01OUTBOX0000000000000000',
        organizationId: '01ORG00000000000000000000',
        userId: '01USER000000000000000000',
        category: 'scheduling',
        channel: 'in_app',
        attempts: 2,
        sentAt: $sentAt,
    );

    expect($event->name())->toBe('notifications.sent')
        ->and($event->module())->toBe('notifications')
        ->and($event->payload()['attempts'])->toBe(2)
        ->and($event->payload()['sent_at'])->toBe($sentAt->toIso8601String());
});

it('carries the final error when delivery failed', function (): void {
    $event = new NotificationFailed(
        outboxId: '01OUTBOX0000000000000000',
        organizationId: '01ORG00000000000000000000',
        userId: '01USER000000000000000000',
        category: 'billing',
        channel: 'email',
        attempts: 3,
        error: 'provider_unreachable',
    );

    expect($event->name())->toBe('notifications.failed')
        ->and($event->payload()['error'])->toBe('provider_unreachable');
});

it('records why a queued notification was cancelled', function (): void {
    $event = new NotificationCancelled(
        outboxId: '01OUTBOX0000000000000000',
        organizationId: '01ORG00000000000000000000',
        userId: '01USER000000000000000000',
        category: 'scheduling',
        channel: 'sms',
        reason: 'session_restored',
    );

    expect($event->name())->toBe('notifications.cancelled')
        ->and($event->payload()['reason'])->toBe('session_restored');
});

it('keeps every payload primitive-only for audit logging', function (): void {
    $scheduledFor = CarbonImmutable::now('UTC');

    $events = [
        new NotificationQueued(
            outboxId: 'a', organizationId: 'b', userId: 'c',
            category: 'system', channel: 'in_app', locale: 'en',
            eventName: 'x', eventId: 'y', idempotencyKey: 'z',
            scheduledFor: $scheduledFor,
        ),
        new NotificationFailed(
            outboxId: 'a', organizationId: 'b', userId: 'c',
            category: 'system', channel: 'in_app', attempts: 1, error: null,
        ),
        new NotificationCancelled(
            outboxId: 'a', organizationId: 'b', userId: 'c',
            category: 'system', channel: 'in_app', reason: 'r',
        ),
    ];

    foreach ($events as $event) {
        foreach ($event->payload() as $value) {
            expect(is_scalar($value) || $value === null)->toBeTrue();
        }
    }

    expect(OutboxStatus::Queued->value)->toBe('queued');
});

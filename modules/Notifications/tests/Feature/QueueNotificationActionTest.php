<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Modules\Notifications\Application\Actions\QueueNotificationAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationQueued;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('queues a notification and announces it', function (): void {
    Event::fake([NotificationQueued::class]);

    $action = app(QueueNotificationAction::class);

    $outbox = $action->execute(
        organizationId: Fixtures::organizationId(),
        userId: Fixtures::userId(),
        category: 'scheduling',
        channel: Channel::InApp,
        eventName: 'sessions.cancelled',
        eventId: (string) str()->ulid(),
        subject: ['ar' => 'إلغاء حصة'],
        body: ['ar' => 'تم إلغاء حصتك.'],
    );

    expect($outbox)->not->toBeNull()
        ->and($outbox->status)->toBe(OutboxStatus::Queued)
        ->and($outbox->attempts)->toBe(0)
        ->and($outbox->channel)->toBe('in_app')
        ->and($outbox->locale)->toBe((string) config('notifications.locale.fallback'));

    Event::assertDispatched(NotificationQueued::class);
});

it('records a duplicate within the idempotency window as suppressed', function (): void {
    Event::fake([NotificationQueued::class]);

    $action = app(QueueNotificationAction::class);
    $eventId = (string) str()->ulid();
    $userId = Fixtures::userId();

    $first = $action->execute(
        organizationId: Fixtures::organizationId(),
        userId: $userId,
        category: 'scheduling',
        channel: Channel::Email,
        eventName: 'sessions.cancelled',
        eventId: $eventId,
        subject: ['ar' => 'أ'],
        body: ['ar' => 'ب'],
    );

    $second = $action->execute(
        organizationId: Fixtures::organizationId(),
        userId: $userId,
        category: 'scheduling',
        channel: Channel::Email,
        eventName: 'sessions.cancelled',
        eventId: $eventId,
        subject: ['ar' => 'أ'],
        body: ['ar' => 'ب'],
    );

    expect($second?->id)->not->toBe($first?->id)
        ->and($first?->status)->toBe(OutboxStatus::Queued)
        ->and($second?->status)->toBe(OutboxStatus::Suppressed)
        ->and(NotificationOutbox::query()->count())->toBe(2);

    Event::assertDispatchedTimes(NotificationQueued::class, 1);
});

it('allows the same event to be queued after the idempotency window', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 12:00:00', 'UTC'));

    $action = app(QueueNotificationAction::class);
    $eventId = (string) str()->ulid();
    $userId = Fixtures::userId();
    $arguments = [
        'organizationId' => Fixtures::organizationId(),
        'userId' => $userId,
        'category' => 'scheduling',
        'channel' => Channel::InApp,
        'eventName' => 'sessions.cancelled',
        'eventId' => $eventId,
        'subject' => [],
        'body' => [],
    ];

    $first = $action->execute(...$arguments);
    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinutes(31));
    $second = $action->execute(...$arguments);

    expect($first?->status)->toBe(OutboxStatus::Queued)
        ->and($second?->status)->toBe(OutboxStatus::Queued)
        ->and(NotificationOutbox::query()->count())->toBe(2);
});

it('queues one entry per channel even from the same event', function (): void {
    $action = app(QueueNotificationAction::class);
    $eventId = (string) str()->ulid();

    $inApp = $action->execute(
        organizationId: Fixtures::organizationId(),
        userId: Fixtures::userId(),
        category: 'system',
        channel: Channel::InApp,
        eventName: 'x.y',
        eventId: $eventId,
        subject: [],
        body: [],
    );

    $email = $action->execute(
        organizationId: Fixtures::organizationId(),
        userId: $inApp->user_id,
        category: 'system',
        channel: Channel::Email,
        eventName: 'x.y',
        eventId: $eventId,
        subject: [],
        body: [],
    );

    expect($email?->id)->not->toBe($inApp->id)
        ->and(NotificationOutbox::query()->count())->toBe(2);
});

it('queues the same event independently for different recipients', function (): void {
    $action = app(QueueNotificationAction::class);
    $eventId = (string) str()->ulid();

    $first = $action->execute(
        organizationId: Fixtures::organizationId(),
        userId: Fixtures::userId(),
        category: 'system_alert',
        channel: Channel::InApp,
        eventName: 'system.alerted',
        eventId: $eventId,
        subject: [],
        body: [],
    );

    $second = $action->execute(
        organizationId: Fixtures::organizationId(),
        userId: Fixtures::userId(),
        category: 'system_alert',
        channel: Channel::InApp,
        eventName: 'system.alerted',
        eventId: $eventId,
        subject: [],
        body: [],
    );

    expect($second?->id)->not->toBe($first?->id)
        ->and($second?->idempotency_key)->not->toBe($first?->idempotency_key)
        ->and(NotificationOutbox::query()->count())->toBe(2);
});

it('skips queueing when the recipient opted out of that category and channel', function (): void {
    Event::fake([NotificationQueued::class]);

    $userId = Fixtures::userId();
    $organizationId = Fixtures::organizationId();

    NotificationPreference::query()->create([
        'organization_id' => $organizationId,
        'user_id' => $userId,
        'category' => 'billing',
        'channel' => 'email',
        'enabled' => false,
        'updated_at' => now(),
    ]);

    $result = app(QueueNotificationAction::class)->execute(
        organizationId: $organizationId,
        userId: $userId,
        category: 'billing',
        channel: Channel::Email,
        eventName: 'billing.invoice_due',
        eventId: (string) str()->ulid(),
        subject: [],
        body: [],
    );

    expect($result)->toBeNull()
        ->and(NotificationOutbox::query()->count())->toBe(0);

    Event::assertNothingDispatched();
});

it('rejects channels that are disabled in configuration', function (): void {
    config(['notifications.channels.enabled' => ['in_app']]);

    QueueNotificationAction::class;

    try {
        app(QueueNotificationAction::class)->execute(
            organizationId: Fixtures::organizationId(),
            userId: Fixtures::userId(),
            category: 'scheduling',
            channel: Channel::Sms,
            eventName: 'x.y',
            eventId: (string) str()->ulid(),
            subject: [],
            body: [],
        );

        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('notifications.channel_disabled');
    }

    expect(NotificationOutbox::query()->count())->toBe(0);
});

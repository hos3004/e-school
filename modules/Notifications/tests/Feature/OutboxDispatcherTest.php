<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationQueued;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

function notificationsDispatcherRecipient(string $locale = 'ar', string $timezone = 'UTC'): string
{
    $userId = Fixtures::userId();

    DB::table('users')->where('id', $userId)->update([
        'locale' => $locale,
        'timezone' => $timezone,
    ]);

    return $userId;
}

beforeEach(function (): void {
    /** @var \Tests\TestCase $this */
    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => true],
            'email' => ['enabled' => true],
            'push' => ['enabled' => false],
        ],
        'notifications.categories.test_notice' => [
            'channels' => ['in_app', 'email', 'push'],
            'critical' => false,
        ],
        'notifications.categories.test_critical' => [
            'channels' => ['in_app', 'email'],
            'critical' => true,
        ],
        'notifications.delivery.idempotency_window_minutes' => 30,
        'notifications.localization.fallback_locale' => 'ar',
        'notifications.quiet_hours.enabled' => false,
    ]);
});

afterEach(function (): void {
    /** @var \Tests\TestCase $this */
    CarbonImmutable::setTestNow();
});

it('writes one outbox row for every recipient and enabled category channel', function (): void {
    /** @var \Tests\TestCase $this */
    $recipients = [
        notificationsDispatcherRecipient(),
        notificationsDispatcherRecipient('en'),
        notificationsDispatcherRecipient('ar', 'Europe/Istanbul'),
    ];

    $written = app(NotificationDispatcher::class)->dispatch(
        'test_notice',
        $recipients,
        ['event_id' => (string) Str::ulid(), 'body' => ['message' => 'test']],
    );

    expect($written)->toBe(6)
        ->and(NotificationOutbox::query()->count())->toBe(6)
        ->and(NotificationOutbox::query()->where('status', OutboxStatus::Queued)->count())->toBe(6)
        ->and(NotificationOutbox::query()->distinct()->pluck('channel')->sort()->values()->all())
        ->toBe(['email', 'in_app']);
});

it('records repeated deliveries as suppressed without announcing them again', function (): void {
    /** @var \Tests\TestCase $this */
    Event::fake([NotificationQueued::class]);

    $recipient = notificationsDispatcherRecipient();
    $eventId = (string) Str::ulid();
    $payload = ['event_id' => $eventId, 'body' => ['message' => 'test']];
    $dispatcher = app(NotificationDispatcher::class);

    expect($dispatcher->dispatch('test_notice', [$recipient], $payload))->toBe(2)
        ->and($dispatcher->dispatch('test_notice', [$recipient], $payload))->toBe(2)
        ->and(NotificationOutbox::query()->where('status', OutboxStatus::Queued)->count())->toBe(2)
        ->and(NotificationOutbox::query()->where('status', OutboxStatus::Suppressed)->count())->toBe(2);

    Event::assertDispatchedTimes(NotificationQueued::class, 2);
});

it('allows a still-queued event to be queued again after the idempotency window', function (): void {
    /** @var \Tests\TestCase $this */
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 10:00:00', 'UTC'));
    config(['notifications.channels' => ['in_app' => ['enabled' => true]]]);

    $recipient = notificationsDispatcherRecipient();
    $payload = ['event_id' => (string) Str::ulid()];
    $dispatcher = app(NotificationDispatcher::class);

    $dispatcher->dispatch('test_notice', [$recipient], $payload);

    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinutes(31));
    $dispatcher->dispatch('test_notice', [$recipient], $payload);

    expect(NotificationOutbox::query()->where('status', OutboxStatus::Queued)->count())->toBe(2)
        ->and(NotificationOutbox::query()->where('status', OutboxStatus::Suppressed)->count())->toBe(0);
});

it('respects non-critical preferences but keeps in-app and critical channels enabled', function (): void {
    /** @var \Tests\TestCase $this */
    $recipient = notificationsDispatcherRecipient();
    $organizationId = Fixtures::organizationId();

    foreach (['test_notice', 'test_critical'] as $category) {
        foreach (['in_app', 'email'] as $channel) {
            NotificationPreference::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $recipient,
                'category' => $category,
                'channel' => $channel,
                'enabled' => false,
                'updated_at' => now(),
            ]);
        }
    }

    $dispatcher = app(NotificationDispatcher::class);

    expect($dispatcher->dispatch('test_notice', [$recipient], [
        'event_id' => (string) Str::ulid(),
    ]))->toBe(1)
        ->and($dispatcher->dispatch('test_critical', [$recipient], [
            'event_id' => (string) Str::ulid(),
        ]))->toBe(2)
        ->and(NotificationOutbox::query()->where('category', 'test_notice')->pluck('channel')->all())
        ->toBe(['in_app'])
        ->and(NotificationOutbox::query()->where('category', 'test_critical')->pluck('channel')->sort()->values()->all())
        ->toBe(['email', 'in_app']);
});

it('delays non-critical notifications until quiet hours end in recipient time', function (): void {
    /** @var \Tests\TestCase $this */
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 20:30:00', 'UTC'));

    config([
        'notifications.channels' => ['in_app' => ['enabled' => true]],
        'notifications.categories.quiet_notice' => [
            'channels' => ['in_app'],
            'critical' => false,
        ],
        'notifications.quiet_hours' => [
            'enabled' => true,
            'start' => '22:00',
            'end' => '07:00',
        ],
    ]);

    $recipient = notificationsDispatcherRecipient('ar', 'Europe/Istanbul');

    app(NotificationDispatcher::class)->dispatch('quiet_notice', [$recipient], [
        'event_id' => (string) Str::ulid(),
    ]);

    expect(NotificationOutbox::query()->sole()->scheduled_for->equalTo(
        CarbonImmutable::parse('2026-08-23 04:00:00', 'UTC'),
    ))->toBeTrue();
});

it('uses the fallback locale when the recipient has no locale', function (): void {
    /** @var \Tests\TestCase $this */
    config(['notifications.localization.fallback_locale' => 'en']);
    $recipient = notificationsDispatcherRecipient('');

    app(NotificationDispatcher::class)->dispatch('test_notice', [$recipient], [
        'event_id' => (string) Str::ulid(),
    ]);

    expect(NotificationOutbox::query()->pluck('locale')->unique()->values()->all())->toBe(['en']);
});

it('rejects categories that are absent from configuration', function (): void {
    /** @var \Tests\TestCase $this */
    try {
        app(NotificationDispatcher::class)->dispatch('missing_category', [], []);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('notifications.category_unknown');
    }

    expect(NotificationOutbox::query()->count())->toBe(0);
});

it('requires the source event id when recipients are present', function (): void {
    /** @var \Tests\TestCase $this */
    $recipient = notificationsDispatcherRecipient();

    try {
        app(NotificationDispatcher::class)->dispatch('test_notice', [$recipient], []);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('notifications.event_id_required');
    }

    expect(NotificationOutbox::query()->count())->toBe(0);
});

it('can exempt configured categories from quiet hours', function (): void {
    /** @var \Tests\TestCase $this */
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-22 23:00:00', 'UTC'));
    config([
        'notifications.channels' => ['in_app' => ['enabled' => true]],
        'notifications.categories.quiet_exempt' => [
            'channels' => ['in_app'],
            'critical' => false,
            'respects_quiet_hours' => false,
        ],
        'notifications.quiet_hours' => [
            'enabled' => true,
            'start' => '22:00',
            'end' => '07:00',
        ],
    ]);

    $recipient = notificationsDispatcherRecipient();

    app(NotificationDispatcher::class)->dispatch('quiet_exempt', [$recipient], [
        'event_id' => (string) Str::ulid(),
    ]);

    expect(NotificationOutbox::query()->sole()->scheduled_for->equalTo(CarbonImmutable::now('UTC')))->toBeTrue();
});

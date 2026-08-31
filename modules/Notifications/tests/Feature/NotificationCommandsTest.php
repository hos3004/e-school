<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Tests\TestCase;

it('dispatches only due queued notifications up to the requested limit', function (): void {
    /** @var TestCase $this */
    Bus::fake([SendQueuedNotification::class]);

    NotificationOutbox::factory()->count(3)->state([
        'scheduled_for' => now('UTC')->subMinute(),
        'status' => OutboxStatus::Queued,
    ])->create();
    NotificationOutbox::factory()->state([
        'scheduled_for' => now('UTC')->addHour(),
        'status' => OutboxStatus::Queued,
    ])->create();
    NotificationOutbox::factory()->sent()->state([
        'scheduled_for' => now('UTC')->subMinute(),
    ])->create();

    $this->artisan('notifications:dispatch-due', ['--limit' => 2])
        ->assertSuccessful();

    Bus::assertDispatchedTimes(SendQueuedNotification::class, 2);
});

it('requeues failed notifications up to the configured command limit', function (): void {
    /** @var TestCase $this */
    Bus::fake([SendQueuedNotification::class]);

    NotificationOutbox::factory()->failed()->count(3)->create();

    $this->artisan('notifications:retry-failed', ['--limit' => 2])
        ->assertSuccessful();

    expect(NotificationOutbox::query()->where('status', OutboxStatus::Queued)->count())->toBe(2)
        ->and(NotificationOutbox::query()->where('status', OutboxStatus::Failed)->count())->toBe(1)
        ->and(NotificationOutbox::query()->where('status', OutboxStatus::Queued)->where('attempts', 0)->count())->toBe(2);

    Bus::assertDispatchedTimes(SendQueuedNotification::class, 2);
});

it('does not automatically retry permanent failures', function (): void {
    /** @var TestCase $this */
    Bus::fake([SendQueuedNotification::class]);

    $permanent = NotificationOutbox::factory()->failed()->state([
        'last_error_retryable' => false,
    ])->create();
    $retryable = NotificationOutbox::factory()->failed()->create();

    $this->artisan('notifications:retry-failed')->assertSuccessful();

    expect($permanent->refresh()->status)->toBe(OutboxStatus::Failed)
        ->and($retryable->refresh()->status)->toBe(OutboxStatus::Queued);

    Bus::assertDispatchedTimes(SendQueuedNotification::class, 1);
});

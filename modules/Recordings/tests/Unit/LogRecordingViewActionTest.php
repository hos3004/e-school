<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Recordings\Application\Actions\LogRecordingViewAction;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingViewed;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingView;
use Modules\Recordings\Tests\Concerns\CreatesRecordingContext;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(CreatesRecordingContext::class);

beforeEach(function (): void {
    $this->context = $this->createSessionWithClassroom();
});

it('logs a view on a ready recording', function (): void {
    Event::fake([RecordingViewed::class]);

    $recording = Recording::factory()->ready()->create($this->context);
    $userId = Fixtures::userId();

    $view = app(LogRecordingViewAction::class)->execute(
        $recording,
        $userId,
        action: 'view',
        ipAddress: '127.0.0.1',
        userAgent: 'Pest/5',
    );

    expect($view->user_id)->toBe($userId)
        ->and($view->action)->toBe('view')
        ->and(RecordingView::query()->whereKey($view->id)->exists())->toBeTrue();

    Event::assertDispatched(
        RecordingViewed::class,
        fn (RecordingViewed $event): bool => $event->userId === $userId && $event->action === 'view',
    );
});

it('rejects viewing a recording that is not ready', function (): void {
    $recording = Recording::factory()->withStatus(RecordingStatus::Processing)->create($this->context);

    app(LogRecordingViewAction::class)->execute($recording, (string) str()->ulid());
})->throws(BusinessRuleViolation::class);

it('rejects viewing a deleted recording even if it was ready', function (): void {
    $recording = Recording::factory()->ready()->create($this->context);
    $recording->delete();

    app(LogRecordingViewAction::class)->execute($recording, (string) str()->ulid());
})->throws(BusinessRuleViolation::class);

it('blocks downloads while the policy forbids them without touching code', function (): void {
    config()->set('recordings.access.allow_download', false);

    $recording = Recording::factory()->ready()->create($this->context);

    app(LogRecordingViewAction::class)->execute($recording, (string) str()->ulid(), action: 'download');
})->throws(BusinessRuleViolation::class);

it('allows downloads once the policy permits them', function (): void {
    config()->set('recordings.access.allow_download', true);

    $recording = Recording::factory()->ready()->create($this->context);

    $view = app(LogRecordingViewAction::class)->execute($recording, Fixtures::userId(), action: 'download');

    expect($view->action)->toBe('download');
});

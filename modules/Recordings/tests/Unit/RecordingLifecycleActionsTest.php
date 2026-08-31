<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Recordings\Application\Actions\ArchiveRecordingAction;
use Modules\Recordings\Application\Actions\DeleteRecordingAction;
use Modules\Recordings\Application\Actions\ExpireRecordingsAction;
use Modules\Recordings\Application\Actions\MarkRecordingReadyAction;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingArchived;
use Modules\Recordings\Domain\Events\RecordingBecameReady;
use Modules\Recordings\Domain\Events\RecordingDeleted;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Tests\Concerns\CreatesRecordingContext;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class, CreatesRecordingContext::class);

beforeEach(function (): void {
    $this->context = $this->createSessionWithClassroom();
});

it('marks a processing recording ready through the state machine', function (): void {
    Event::fake([RecordingBecameReady::class]);

    $recording = Recording::factory()->withStatus(RecordingStatus::Processing)->create($this->context);

    $updated = app(MarkRecordingReadyAction::class)->execute($recording, durationSeconds: 2700);

    expect($updated->status)->toBe(RecordingStatus::Ready)
        ->and($updated->duration_seconds)->toBe(2700)
        ->and($recording->refresh()->status->isWatchable())->toBeTrue();

    Event::assertDispatched(RecordingBecameReady::class);
});

it('rejects skipping the lifecycle from archived back to ready', function (): void {
    $recording = Recording::factory()->withStatus(RecordingStatus::Archived)->create($this->context);

    app(MarkRecordingReadyAction::class)->execute($recording);
})->throws(BusinessRuleViolation::class);

it('archives a ready recording with the configured driver and stamps the archive time', function (): void {
    config()->set('recordings.storage.archive_driver', 'google_drive');
    Event::fake([RecordingArchived::class]);

    $recording = Recording::factory()->ready()->create($this->context);

    $updated = app(ArchiveRecordingAction::class)->execute(
        $recording,
        archivePath: 'cold/{year}/1.mp4',
    );

    expect($updated->status)->toBe(RecordingStatus::Archived)
        ->and($updated->archive_driver)->toBe('google_drive')
        ->and($updated->archive_path)->toBe('cold/{year}/1.mp4')
        ->and($updated->archived_at)->toBeInstanceOf(CarbonImmutable::class);

    Event::assertDispatched(RecordingArchived::class);
});

it('refuses archiving when no driver is configured', function (): void {
    config()->set('recordings.storage.archive_driver', 'none');

    $recording = Recording::factory()->ready()->create($this->context);

    app(ArchiveRecordingAction::class)->execute($recording);
})->throws(BusinessRuleViolation::class);

it('expires recordings past retention according to the configured policy', function (): void {
    config()->set('recordings.on_expiry', 'delete');

    $pastReady = Recording::factory()->pastRetention()->create($this->context);
    $withinRetention = Recording::factory()->ready()->create($this->context);

    $processed = app(ExpireRecordingsAction::class)->execute();

    expect($processed)->toContain((string) $pastReady->id)
        ->and(in_array((string) $withinRetention->id, $processed, true))->toBeFalse()
        ->and($pastReady->refresh()->status)->toBe(RecordingStatus::Expired)
        ->and($withinRetention->refresh()->status)->toBe(RecordingStatus::Ready);
});

it('archives first when the expiry policy is archive_then_delete', function (): void {
    config()->set('recordings.storage.archive_driver', 'google_drive');
    config()->set('recordings.on_expiry', 'archive_then_delete');

    $pastReady = Recording::factory()->pastRetention()->create($this->context);

    app(ExpireRecordingsAction::class)->execute();

    expect($pastReady->refresh()->status)->toBe(RecordingStatus::Archived);
});

it('soft deletes with a documented reason and records who did it', function (): void {
    Event::fake([RecordingDeleted::class]);

    $recording = Recording::factory()->ready()->create($this->context);
    $actorId = Fixtures::userId();

    $deleted = app(DeleteRecordingAction::class)->execute($recording, 'طلب اعتراض من ولي الأمر', $actorId);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->deleted_by)->toBe($actorId)
        ->and((string) $deleted->deletion_reason)->toContain('اعتراض')
        ->and(Recording::query()->whereKey($recording->id)->exists())->toBeFalse()
        ->and(Recording::withTrashed()->whereKey($recording->id)->exists())->toBeTrue();

    Event::assertDispatched(RecordingDeleted::class);
});

it('requires an actor for deletion', function (): void {
    $recording = Recording::factory()->ready()->create($this->context);

    app(DeleteRecordingAction::class)->execute($recording, 'بلا معرّف منفّذ', null);
})->throws(BusinessRuleViolation::class);

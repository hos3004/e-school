<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Modules\Recordings\Application\Actions\RegisterRecordingAction;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingRegistered;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Tests\Concerns\CreatesRecordingContext;
use Shared\Support\BusinessRuleViolation;

uses(CreatesRecordingContext::class);

beforeEach(function (): void {
    $this->context = $this->createSessionWithClassroom();
});

it('registers a recording with retention from config and publishes the event', function (): void {
    config()->set('recordings.retention_days', 30);
    Event::fake([RecordingRegistered::class]);

    $recording = app(RegisterRecordingAction::class)->execute(
        organizationId: $this->organizationId,
        sessionId: $this->context['session_id'],
        classroomId: $this->context['classroom_id'],
        provider: 'bigbluebutton',
        externalRecordingId: 'bbb-rec-1',
        disk: 'r2',
        path: 'recordings/test/1.mp4',
        durationSeconds: 3600,
        sizeBytes: 350_000_000,
    );

    expect($recording->status)->toBe(RecordingStatus::Processing)
        ->and($recording->available_from)->toBeInstanceOf(CarbonImmutable::class)
        ->and((int) ceil($recording->available_from->diffInDays($recording->expires_at)))->toBe(30)
        ->and(Recording::query()->whereKey($recording->id)->exists())->toBeTrue();

    Event::assertDispatched(RecordingRegistered::class);
});

it('respects a changed retention policy without touching code', function (): void {
    config()->set('recordings.retention_days', 7);

    $recording = app(RegisterRecordingAction::class)->execute(
        organizationId: $this->organizationId,
        sessionId: $this->context['session_id'],
        classroomId: $this->context['classroom_id'],
        provider: 'bigbluebutton',
        externalRecordingId: 'bbb-retention-7',
        disk: 'r2',
        path: 'recordings/test/7.mp4',
    );

    expect(round($recording->available_from->diffInDays($recording->expires_at)))->toBe(7.0);
});

it('rejects duplicate provider external id even among soft deleted rows', function (): void {
    $action = app(RegisterRecordingAction::class);

    $action->execute(
        organizationId: $this->organizationId,
        sessionId: $this->context['session_id'],
        classroomId: $this->context['classroom_id'],
        provider: 'bigbluebutton',
        externalRecordingId: 'dup-1',
        disk: 'r2',
        path: 'recordings/test/dup.mp4',
    );

    Recording::query()->where('external_recording_id', 'dup-1')->first()?->delete();

    expect(
        Recording::withTrashed()->where('external_recording_id', 'dup-1')->count(),
    )->toBe(1);

    $action->execute(
        organizationId: $this->organizationId,
        sessionId: $this->context['session_id'],
        classroomId: $this->context['classroom_id'],
        provider: 'bigbluebutton',
        externalRecordingId: 'dup-1',
        disk: 'r2',
        path: 'recordings/test/dup2.mp4',
    );
})->throws(BusinessRuleViolation::class);

it('does not publish the event when the guard fails and no row leaks', function (): void {
    Event::fake([RecordingRegistered::class]);

    $action = app(RegisterRecordingAction::class);

    $action->execute(
        organizationId: $this->organizationId,
        sessionId: $this->context['session_id'],
        classroomId: $this->context['classroom_id'],
        provider: 'bigbluebutton',
        externalRecordingId: 'guard-1',
        disk: 'r2',
        path: 'recordings/test/guard.mp4',
    );

    try {
        $action->execute(
            organizationId: $this->organizationId,
            sessionId: $this->context['session_id'],
            classroomId: $this->context['classroom_id'],
            provider: 'bigbluebutton',
            externalRecordingId: 'guard-1',
            disk: 'r2',
            path: 'recordings/test/guard-again.mp4',
        );
        $this->fail('Duplicate registration should have been rejected.');
    } catch (BusinessRuleViolation) {
    }

    expect(Recording::query()->where('external_recording_id', 'guard-1')->count())->toBe(1)
        ->and(Recording::query()->where('path', 'recordings/test/guard-again.mp4')->exists())->toBeFalse();

    Event::assertDispatchedTimes(RecordingRegistered::class, 1);
});

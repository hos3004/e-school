<?php

declare(strict_types=1);

use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Events\RecordingArchived;
use Modules\Recordings\Domain\Events\RecordingBecameReady;
use Modules\Recordings\Domain\Events\RecordingDeleted;
use Modules\Recordings\Domain\Events\RecordingExpired;
use Modules\Recordings\Domain\Events\RecordingRegistered;
use Modules\Recordings\Domain\Events\RecordingViewed;
use Modules\Recordings\Domain\Models\Recording;

it('exposes stable names, the owning module and primitive payloads', function (): void {
    $payloadOnlyPrimitives = fn (array $payload): bool => collect($payload)
        ->every(fn (mixed $value): bool => is_scalar($value) || $value === null);

    $registered = new RecordingRegistered(
        recordingId: '01R0000000000000000000000',
        organizationId: '01O0000000000000000000000',
        sessionId: '01S0000000000000000000000',
        classroomId: '01C0000000000000000000000',
        provider: 'zoom',
        externalRecordingId: 'ext-1',
        expiresAt: '2026-02-01T00:00:00+00:00',
    );

    expect($registered->name())->toBe('recordings.registered')
        ->and($registered->module())->toBe('Recordings')
        ->and($registered->payload())->toBeArray()
        ->and($payloadOnlyPrimitives($registered->payload()))->toBeTrue();

    $ready = new RecordingBecameReady('01R', '01O', '01S', 1800, 100_000_000);
    expect($ready->name())->toBe('recordings.became_ready')
        ->and($payloadOnlyPrimitives($ready->payload()))->toBeTrue();

    $archived = new RecordingArchived('01R', '01O', '01S', 'google_drive', '2026-02-02T00:00:00+00:00');
    expect($archived->name())->toBe('recordings.archived');

    $expired = new RecordingExpired('01R', '01O', '01S', '2026-02-03T00:00:00+00:00');
    expect($expired->name())->toBe('recordings.expired');

    $deleted = new RecordingDeleted('01R', '01O', '01S', '01U', 'سبب');
    expect($deleted->name())->toBe('recordings.deleted');

    $viewed = new RecordingViewed('01R', '01O', '01S', '01U', 'view');
    expect($viewed->name())->toBe('recordings.viewed');
});

it('casts status to the enum on the model', function (): void {
    $recording = Recording::factory()->ready()->make();

    expect($recording->status)->toBeInstanceOf(RecordingStatus::class)
        ->and($recording->status)->toBe(RecordingStatus::Ready);
});

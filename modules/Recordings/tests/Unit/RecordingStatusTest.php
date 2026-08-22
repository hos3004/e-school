<?php

declare(strict_types=1);

use Modules\Recordings\Domain\Enums\RecordingStatus;

it('allows only the documented lifecycle transitions', function (): void {
    expect(RecordingStatus::Processing->canTransitionTo(RecordingStatus::Ready))->toBeTrue()
        ->and(RecordingStatus::Processing->canTransitionTo(RecordingStatus::Failed))->toBeTrue()
        ->and(RecordingStatus::Ready->canTransitionTo(RecordingStatus::Archived))->toBeTrue()
        ->and(RecordingStatus::Ready->canTransitionTo(RecordingStatus::Expired))->toBeTrue()
        ->and(RecordingStatus::Archived->canTransitionTo(RecordingStatus::Expired))->toBeTrue();
});

it('rejects undocumented transitions', function (): void {
    expect(RecordingStatus::Processing->canTransitionTo(RecordingStatus::Archived))->toBeFalse()
        ->and(RecordingStatus::Ready->canTransitionTo(RecordingStatus::Processing))->toBeFalse()
        ->and(RecordingStatus::Failed->canTransitionTo(RecordingStatus::Ready))->toBeFalse()
        ->and(RecordingStatus::Expired->canTransitionTo(RecordingStatus::Ready))->toBeFalse()
        ->and(RecordingStatus::Ready->canTransitionTo(RecordingStatus::Failed))->toBeFalse();
});

it('marks terminal states and watchability', function (): void {
    expect(RecordingStatus::Failed->isTerminal())->toBeTrue()
        ->and(RecordingStatus::Expired->isTerminal())->toBeTrue()
        ->and(RecordingStatus::Ready->isTerminal())->toBeFalse()
        ->and(RecordingStatus::Ready->isWatchable())->toBeTrue()
        ->and(RecordingStatus::Processing->isWatchable())->toBeFalse()
        ->and(RecordingStatus::Archived->isWatchable())->toBeFalse();
});

it('labels every status through translations', function (): void {
    foreach (RecordingStatus::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty()
            ->and(__('recordings::status.'.$status->value))->toBe($status->label());
    }
});

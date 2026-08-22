<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Recordings\Application\Policies\RecordingPolicy;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Tests\Concerns\CreatesRecordingContext;
use Modules\Recordings\Tests\Support\ApiUser;

uses(CreatesRecordingContext::class);

beforeEach(function (): void {
    $this->context = $this->createSessionWithClassroom();
});

it('scopes every action to the same organization', function (): void {
    Gate::define('recordings.recording.view', fn (): bool => true);

    $policy = new RecordingPolicy;

    $recording = Recording::factory()->ready()->create($this->context);

    $owner = new ApiUser('01USEROWNER00000000000000', (string) $recording->organization_id);
    $stranger = new ApiUser('01USERSTRANGER0000000000', '01OTHERORGANIZATION000000');

    expect($policy->view($owner, $recording))->toBeTrue()
        ->and($policy->view($stranger, $recording))->toBeFalse()
        ->and($policy->update($owner, $recording))->toBeFalse()
        ->and($policy->delete($stranger, $recording))->toBeFalse()
        ->and($policy->watch($stranger, $recording))->toBeFalse()
        ->and($policy->manageLifecycle($stranger, $recording))->toBeFalse();
});

it('grants abilities only through the gate, never through role names', function (): void {
    $policy = new RecordingPolicy;
    $user = new ApiUser('01POLICYUSER000000000000', '01POLICYORG000000000000');

    Gate::define('recordings.recording.view_any', fn (): bool => false);
    Gate::define('recordings.recording.create', fn (): bool => false);
    Gate::define('recordings.recording.update', fn (): bool => true);

    $recording = Recording::factory()->make([
        'organization_id' => $user->organization_id,
        'status' => RecordingStatus::Ready,
    ]);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $recording))->toBeTrue();
});

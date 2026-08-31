<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Recordings\Application\Policies\RecordingPolicy;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Tests\Concerns\CreatesRecordingContext;
use Modules\Recordings\Tests\Support\ApiUser;

uses(RefreshDatabase::class, CreatesRecordingContext::class);

beforeEach(function (): void {
    $this->context = $this->createSessionWithClassroom();
});

it('scopes every action to the same organization', function (): void {
    Gate::define('recording.view', fn (): bool => true);
    Gate::define('recording.view.any', fn (): bool => false);

    $policy = app(RecordingPolicy::class);

    $recording = Recording::factory()->ready()->create($this->context);

    $teacherUserId = (string) DB::table('staff_profiles')
        ->where('id', DB::table('sessions')->where('id', $recording->session_id)->value('staff_profile_id'))
        ->value('user_id');
    $owner = User::query()->findOrFail($teacherUserId);
    $stranger = new ApiUser('01USERSTRANGER0000000000', '01OTHERORGANIZATION000000');

    expect($policy->view($owner, $recording))->toBeTrue()
        ->and($policy->view($stranger, $recording))->toBeFalse()
        ->and($policy->update($owner, $recording))->toBeFalse()
        ->and($policy->delete($stranger, $recording))->toBeFalse()
        ->and($policy->watch($stranger, $recording))->toBeFalse()
        ->and($policy->manageLifecycle($stranger, $recording))->toBeFalse();
});

it('grants abilities only through the gate, never through role names', function (): void {
    $policy = app(RecordingPolicy::class);
    $user = new ApiUser('01POLICYUSER000000000000', '01POLICYORG000000000000');

    Gate::define('recording.view.any', fn (): bool => false);
    Gate::define('recording.delete', fn (): bool => true);

    $recording = Recording::factory()->make([
        'organization_id' => $user->organization_id,
        'status' => RecordingStatus::Ready,
    ]);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $recording))->toBeFalse()
        ->and($policy->delete($user, $recording))->toBeTrue();
});

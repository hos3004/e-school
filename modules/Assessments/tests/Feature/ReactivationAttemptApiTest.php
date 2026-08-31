<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Domain\Models\Question;
use Modules\Discipline\Database\Factories\ReactivationRequestFactory;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('assessment.take', fn ($user): bool => false);
});

function reactivationApiAssessment(): Assessment
{
    $assessment = Assessment::factory()->availableNow()->create([
        'type' => 'reactivation',
        'course_id' => null,
        'total_score' => 100,
        'passing_score' => 50,
    ]);

    Question::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'score' => 100,
        'sort_order' => 1,
    ]);

    return $assessment->refresh();
}

/** @return array{0: User, 1: string} */
function reactivationApiActor(): array
{
    $user = User::factory()->inOrganization(Fixtures::organizationId())->create();

    return [$user, Fixtures::studentProfileForUser((string) $user->getKey())];
}

it('starts a reactivation attempt for the owning pending request', function (): void {
    [$user, $studentProfileId] = reactivationApiActor();
    Gate::define('assessment.take', fn ($candidate): bool => $candidate->is($user));
    $assessment = reactivationApiAssessment();
    $reactivation = ReactivationRequestFactory::new()->create([
        'organization_id' => (string) $user->organization_id,
        'requested_by' => (string) $user->getKey(),
        'status' => ReactivationStatus::Pending,
    ]);

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts", [
            'reactivation_request_id' => (string) $reactivation->getKey(),
        ])
        ->assertCreated();

    $attempt = AssessmentAttempt::query()->sole();
    expect($attempt->student_profile_id)->toBe($studentProfileId)
        ->and($attempt->reactivation_request_id)->toBe((string) $reactivation->getKey());
});

it('rejects an unknown reactivation request', function (): void {
    [$user] = reactivationApiActor();
    Gate::define('assessment.take', fn ($candidate): bool => $candidate->is($user));
    $assessment = reactivationApiAssessment();

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts", [
            'reactivation_request_id' => (string) Str::ulid(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'assessments.reactivation_request_not_found');
});

it('rejects a reactivation request outside pending state', function (): void {
    [$user] = reactivationApiActor();
    Gate::define('assessment.take', fn ($candidate): bool => $candidate->is($user));
    $assessment = reactivationApiAssessment();
    $reactivation = ReactivationRequestFactory::new()->create([
        'organization_id' => (string) $user->organization_id,
        'requested_by' => (string) $user->getKey(),
        'status' => ReactivationStatus::Rejected,
    ]);

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts", [
            'reactivation_request_id' => (string) $reactivation->getKey(),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'assessments.reactivation_request_invalid_state');
});

it('forbids a reactivation request from another organization', function (): void {
    [$user] = reactivationApiActor();
    Gate::define('assessment.take', fn ($candidate): bool => $candidate->is($user));
    $assessment = reactivationApiAssessment();
    $foreign = Organization::factory()->create();
    $reactivation = ReactivationRequestFactory::new()->create([
        'organization_id' => (string) $foreign->getKey(),
        'requested_by' => (string) $user->getKey(),
        'status' => ReactivationStatus::Pending,
    ]);

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts", [
            'reactivation_request_id' => (string) $reactivation->getKey(),
        ])
        ->assertForbidden();
});

it('forbids starting another users reactivation request', function (): void {
    [$user] = reactivationApiActor();
    Gate::define('assessment.take', fn ($candidate): bool => $candidate->is($user));
    $assessment = reactivationApiAssessment();
    $other = User::factory()->inOrganization((string) $user->organization_id)->create();
    $reactivation = ReactivationRequestFactory::new()->create([
        'organization_id' => (string) $user->organization_id,
        'requested_by' => (string) $other->getKey(),
        'status' => ReactivationStatus::Pending,
    ]);

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts", [
            'reactivation_request_id' => (string) $reactivation->getKey(),
        ])
        ->assertForbidden();
});

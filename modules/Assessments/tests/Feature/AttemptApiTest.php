<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('assessments.assessment.view', fn ($user) => true);
    Gate::define('assessments.attempt.start', fn ($user) => true);
    Gate::define('assessments.attempt.submit', fn ($user) => true);
    Gate::define('assessments.attempt.grade', fn ($user) => true);
});

function attemptApiUser(): User
{
    return User::factory()->create();
}

it('starts an attempt through the API and returns the attempt resource', function (): void {
    $assessment = Assessment::factory()->availableNow()->create();

    $this->actingAs(attemptApiUser())
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts", [
            'student_profile_id' => Fixtures::studentProfileId(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.attempt_number', 1)
        ->assertJsonPath('data.submitted_at', null);
});

it('submits an attempt and hides answers from non-graders', function (): void {
    Gate::define('assessments.attempt.grade', fn ($user) => false);

    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => Assessment::factory()->availableNow()->create()->getKey(),
        'started_at' => now('UTC')->subMinutes(5),
    ]);

    $this->actingAs(attemptApiUser())
        ->postJson("/api/attempts/{$attempt->getKey()}/submit", [
            'answers' => ['q1' => ['key' => 'b']],
        ])
        ->assertOk()
        ->assertJsonMissing(['answers']);
});

it('grades a submitted attempt through the API', function (): void {
    $assessment = Assessment::factory()->create(['total_score' => 100, 'passing_score' => 50]);

    $attempt = AssessmentAttempt::factory()
        ->submitted()
        ->create(['assessment_id' => $assessment->getKey()]);

    $this->actingAs(attemptApiUser())
        ->postJson("/api/attempts/{$attempt->getKey()}/grade", [
            'score' => 65,
        ])
        ->assertOk()
        ->assertJsonPath('data.score', 65)
        ->assertJsonPath('data.passed', true);
});

it('forbids grading for callers without the grade permission', function (): void {
    Gate::define('assessments.attempt.grade', fn ($user) => false);

    $attempt = AssessmentAttempt::factory()
        ->submitted()
        ->create([
            'assessment_id' => Assessment::factory()->create()->getKey(),
        ]);

    $this->actingAs(attemptApiUser())
        ->postJson("/api/attempts/{$attempt->getKey()}/grade", ['score' => 90])
        ->assertForbidden();
});

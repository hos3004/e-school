<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Domain\Models\Question;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // These are the canonical permissions from AccessControlSeeder and
    // docs/06-permissions-matrix.md. Deny by default so every happy path has
    // to grant exactly the capability it exercises.
    Gate::define('assessment.take', fn ($user): bool => false);
    Gate::define('assessment.manage', fn ($user): bool => false);
    Gate::define('grade.view', fn ($user): bool => false);
    Gate::define('program.manage', fn ($user): bool => false);
});

/** @return array{0: User, 1: string} */
function attemptApiStudent(): array
{
    $user = User::factory()->inOrganization(Fixtures::organizationId())->create();

    return [$user, Fixtures::studentProfileForUser((string) $user->getKey())];
}

/**
 * @param array<string, mixed> $attributes
 * @return array{0: Assessment, 1: Question}
 */
function attemptApiAssessmentWithQuestion(array $attributes = []): array
{
    $assessment = Assessment::factory()->availableNow()->create(array_merge([
        // Placement assessments intentionally have no course, so this API
        // authorization suite does not need to fabricate an enrollment.
        'type' => 'placement',
        'course_id' => null,
        'total_score' => 100,
        'passing_score' => 50,
    ], $attributes));

    $question = Question::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'score' => (int) $assessment->total_score,
        'sort_order' => 1,
    ]);

    return [$assessment->refresh(), $question];
}

function attemptApiAllowCourseEnrollment(string $studentProfileId): void
{
    app()->instance(EnrollmentAdministrationQueries::class, new readonly class($studentProfileId) implements EnrollmentAdministrationQueries
    {
        public function __construct(private string $studentProfileId) {}

        public function forStudent(string $organizationId, string $studentProfileId): array
        {
            return [];
        }

        public function schedulableEnrollmentIdsByStudent(
            string $organizationId,
            string $programId,
            array $studentProfileIds = [],
        ): array {
            return in_array($this->studentProfileId, $studentProfileIds, true)
                ? [$this->studentProfileId => 'test-active-enrollment']
                : [];
        }
    });
}

function attemptApiForeignOrganizationId(): string
{
    $organizationId = (string) Str::ulid();

    DB::table('organizations')->insert([
        'id' => $organizationId,
        'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Another organization'], JSON_UNESCAPED_UNICODE),
        'slug' => 'attempt-foreign-'.strtolower(substr($organizationId, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $organizationId;
}

it('starts an attempt through the API for the authenticated student profile', function (): void {
    Gate::define('assessment.take', fn ($user): bool => true);
    [$user, $studentProfileId] = attemptApiStudent();
    attemptApiAllowCourseEnrollment($studentProfileId);
    [$assessment] = attemptApiAssessmentWithQuestion([
        'type' => 'quiz',
        'course_id' => Fixtures::courseId(),
    ]);
    $spoofedStudentProfileId = Fixtures::studentProfileId();

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts", [
            // The server derives ownership from the actor and ignores this
            // legacy/spoofable field instead of starting for another student.
            'student_profile_id' => $spoofedStudentProfileId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.student_profile_id', $studentProfileId)
        ->assertJsonPath('data.attempt_number', 1)
        ->assertJsonPath('data.submitted_at', null);
});

it('rejects starting a course assessment without a schedulable enrollment', function (): void {
    Gate::define('assessment.take', fn ($user): bool => true);
    [$user] = attemptApiStudent();
    [$assessment] = attemptApiAssessmentWithQuestion([
        'type' => 'quiz',
        'course_id' => Fixtures::courseId(),
    ]);

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'assessments.student_not_eligible');
});

it('submits an owned attempt and hides answers from non-graders', function (): void {
    Gate::define('assessment.take', fn ($user): bool => true);
    [$user, $studentProfileId] = attemptApiStudent();
    [$assessment, $question] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'student_profile_id' => $studentProfileId,
        'started_at' => now('UTC')->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->postJson("/api/attempts/{$attempt->getKey()}/submit", [
            'answers' => [(string) $question->getKey() => ['key' => 'b']],
        ])
        ->assertOk()
        ->assertJsonMissing(['answers']);
});

it('grades a submitted attempt through the API', function (): void {
    Gate::define('assessment.manage', fn ($user): bool => true);
    Gate::define('program.manage', fn ($user): bool => true);
    $grader = User::factory()->inOrganization(Fixtures::organizationId())->create();
    [$assessment] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()
        ->submitted()
        ->create(['assessment_id' => $assessment->getKey()]);

    $this->actingAs($grader)
        ->postJson("/api/attempts/{$attempt->getKey()}/grade", [
            'score' => 65,
            'reason' => 'تصحيح المحاولة وفق نموذج الإجابة المعتمد',
        ])
        ->assertOk()
        ->assertJsonPath('data.score', 65)
        ->assertJsonPath('data.passed', true);
});

it('forbids grading for callers without the manage permission', function (): void {
    $grader = User::factory()->inOrganization(Fixtures::organizationId())->create();
    [$assessment] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()
        ->submitted()
        ->create(['assessment_id' => $assessment->getKey()]);

    $this->actingAs($grader)
        ->postJson("/api/attempts/{$attempt->getKey()}/grade", [
            'score' => 90,
            'reason' => 'طلب تصحيح غير مصرح به',
        ])
        ->assertForbidden();
});

it('requires authentication to start an attempt', function (): void {
    [$assessment] = attemptApiAssessmentWithQuestion();

    $this->postJson("/api/assessments/{$assessment->getKey()}/attempts")
        ->assertUnauthorized();
});

it('forbids authenticated callers without take permission from starting attempts', function (): void {
    [$user] = attemptApiStudent();
    [$assessment] = attemptApiAssessmentWithQuestion();

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts")
        ->assertForbidden();
});

it('forbids starting an attempt for an assessment in another organization', function (): void {
    Gate::define('assessment.take', fn ($user): bool => true);
    [$assessment] = attemptApiAssessmentWithQuestion();
    $outsider = User::factory()
        ->inOrganization(attemptApiForeignOrganizationId())
        ->create();

    $this->actingAs($outsider)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts")
        ->assertForbidden();
});

it('rejects starting an attempt with an incomplete question bank', function (): void {
    Gate::define('assessment.take', fn ($user): bool => true);
    [$user] = attemptApiStudent();
    $assessment = Assessment::factory()->availableNow()->create([
        'type' => 'placement',
        'course_id' => null,
    ]);

    $this->actingAs($user)
        ->postJson("/api/assessments/{$assessment->getKey()}/attempts")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'assessments.question_bank_incomplete');
});

it('requires authentication to submit an attempt', function (): void {
    [$assessment] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->create(['assessment_id' => $assessment->getKey()]);

    $this->postJson("/api/attempts/{$attempt->getKey()}/submit", ['answers' => []])
        ->assertUnauthorized();
});

it('forbids authenticated callers without take permission from submitting attempts', function (): void {
    [$user, $studentProfileId] = attemptApiStudent();
    [$assessment] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'student_profile_id' => $studentProfileId,
    ]);

    $this->actingAs($user)
        ->postJson("/api/attempts/{$attempt->getKey()}/submit", ['answers' => []])
        ->assertForbidden();
});

it('forbids a student from submitting another students attempt', function (): void {
    Gate::define('assessment.take', fn ($user): bool => true);
    [$owner, $ownerProfileId] = attemptApiStudent();
    [$otherStudent] = attemptApiStudent();
    [$assessment, $question] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'student_profile_id' => $ownerProfileId,
    ]);

    $this->actingAs($otherStudent)
        ->postJson("/api/attempts/{$attempt->getKey()}/submit", [
            'answers' => [(string) $question->getKey() => ['key' => 'a']],
        ])
        ->assertForbidden();

    expect($owner->getKey())->not->toBe($otherStudent->getKey());
});

it('forbids submitting an attempt from another organization', function (): void {
    Gate::define('assessment.take', fn ($user): bool => true);
    [$assessment, $question] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->create(['assessment_id' => $assessment->getKey()]);
    $outsider = User::factory()
        ->inOrganization(attemptApiForeignOrganizationId())
        ->create();

    $this->actingAs($outsider)
        ->postJson("/api/attempts/{$attempt->getKey()}/submit", [
            'answers' => [(string) $question->getKey() => ['key' => 'a']],
        ])
        ->assertForbidden();
});

it('rejects submitting an attempt twice', function (): void {
    Gate::define('assessment.take', fn ($user): bool => true);
    [$user, $studentProfileId] = attemptApiStudent();
    [$assessment, $question] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $assessment->getKey(),
        'student_profile_id' => $studentProfileId,
    ]);

    $this->actingAs($user)
        ->postJson("/api/attempts/{$attempt->getKey()}/submit", [
            'answers' => [(string) $question->getKey() => ['key' => 'a']],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'assessments.attempt_already_submitted');
});

it('requires authentication to grade an attempt', function (): void {
    [$assessment] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $assessment->getKey(),
    ]);

    $this->postJson("/api/attempts/{$attempt->getKey()}/grade", [
        'score' => 65,
        'reason' => 'طلب ضيف',
    ])->assertUnauthorized();
});

it('forbids grading an attempt from another organization', function (): void {
    Gate::define('assessment.manage', fn ($user): bool => true);
    Gate::define('program.manage', fn ($user): bool => true);
    [$assessment] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->submitted()->create([
        'assessment_id' => $assessment->getKey(),
    ]);
    $outsider = User::factory()
        ->inOrganization(attemptApiForeignOrganizationId())
        ->create();

    $this->actingAs($outsider)
        ->postJson("/api/attempts/{$attempt->getKey()}/grade", [
            'score' => 65,
            'reason' => 'محاولة تصحيح من مؤسسة أخرى',
        ])
        ->assertForbidden();
});

it('rejects grading before the attempt is submitted', function (): void {
    Gate::define('assessment.manage', fn ($user): bool => true);
    Gate::define('program.manage', fn ($user): bool => true);
    $grader = User::factory()->inOrganization(Fixtures::organizationId())->create();
    [$assessment] = attemptApiAssessmentWithQuestion();
    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
    ]);

    $this->actingAs($grader)
        ->postJson("/api/attempts/{$attempt->getKey()}/grade", [
            'score' => 65,
            'reason' => 'محاولة تصحيح قبل التسليم',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'assessments.grade_before_submission');
});

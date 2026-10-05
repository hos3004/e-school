<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Policies\AssessmentAttemptPolicy;
use Modules\Assessments\Application\Policies\AssessmentManagementScope;
use Modules\Assessments\Application\Policies\AssessmentPolicy;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Tests\Support\ApiUser;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\ValueObjects\TeacherGroupAssignmentData;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

function assessmentsPolicyUser(string $organizationId, bool $granted): ApiUser
{
    Gate::after(fn (): bool => $granted);

    return new ApiUser($organizationId);
}

it('grants assessment abilities only within the same organization', function (): void {
    Gate::after(fn (): bool => true);

    $policy = app(AssessmentPolicy::class);
    $assessment = Assessment::factory()->make();

    expect($policy->viewAny(assessmentsPolicyUser($assessment->organization_id, false)))->toBeTrue()
        ->and($policy->view(assessmentsPolicyUser($assessment->organization_id, false), $assessment))->toBeTrue()
        ->and($policy->create(assessmentsPolicyUser($assessment->organization_id, false)))->toBeTrue()
        ->and($policy->update(assessmentsPolicyUser($assessment->organization_id, false), $assessment))->toBeTrue()
        ->and($policy->delete(assessmentsPolicyUser($assessment->organization_id, false), $assessment))->toBeTrue()
        ->and($policy->manageQuestions(assessmentsPolicyUser($assessment->organization_id, false), $assessment))->toBeTrue()
        // مؤسسة أخرى — تُرفض حتى مع منح الصلاحية العامة.
        ->and($policy->view(assessmentsPolicyUser((string) str()->ulid(), true), $assessment))->toBeFalse()
        ->and($policy->update(assessmentsPolicyUser((string) str()->ulid(), true), $assessment))->toBeFalse()
        ->and($policy->manageQuestions(assessmentsPolicyUser((string) str()->ulid(), true), $assessment))->toBeFalse();
});

it('scopes attempt abilities to the owning organization and the owning student', function (): void {
    // كل القدرات ممنوحة، فيبقى الفارق الوحيد هو المؤسسة وملكية المحاولة.
    Gate::after(fn (): bool => true);

    // السياسة تحقن StudentDirectoryQueries، فتُبنى من الحاوية لا بـ new.
    $policy = app(AssessmentAttemptPolicy::class);
    $assessment = Assessment::factory()->create();
    $ownerUserId = Fixtures::userId();

    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'student_profile_id' => Fixtures::studentProfileForUser($ownerUserId),
    ]);

    $owningOrg = (string) $assessment->organization_id;
    $owner = new ApiUser($owningOrg, $ownerUserId);
    $anotherStudent = new ApiUser($owningOrg, Fixtures::userId());
    $outsider = new ApiUser((string) str()->ulid(), Fixtures::userId());

    expect($policy->viewAny($owner))->toBeTrue()
        ->and($policy->view($owner, $attempt))->toBeTrue()
        // التسليم لصاحب المحاولة وحده — لا لطالب آخر في نفس المؤسسة.
        ->and($policy->submit($owner, $attempt))->toBeTrue()
        ->and($policy->submit($anotherStudent, $attempt))->toBeFalse()
        ->and($policy->grade($owner, $attempt))->toBeTrue()
        // لا حذف للمحاولات إطلاقًا — سجل أكاديمي.
        ->and($policy->delete($owner, $attempt))->toBeFalse()
        ->and($policy->grade($outsider, $attempt))->toBeFalse();
});

it('limits teacher assessment management and grading to a current assigned course', function (): void {
    Gate::define('assessment.manage', fn ($user): bool => true);
    Gate::define('program.manage', fn ($user): bool => false);

    $organizationId = Fixtures::organizationId();
    $courseId = (string) str()->ulid();
    $user = new ApiUser($organizationId, 'teacher-user');
    $assessment = Assessment::factory()->make([
        'organization_id' => $organizationId,
        'course_id' => $courseId,
    ]);

    $staff = Mockery::mock(StaffQueries::class);
    $staff->shouldReceive('findActiveProfileForUser')
        ->with('teacher-user')
        ->andReturn(['id' => 'staff-1', 'staff_code' => 'T1']);

    $groups = Mockery::mock(GroupAdministrationQueries::class);
    $groups->shouldReceive('assignmentsForTeacher')
        ->with($organizationId, 'staff-1')
        ->andReturn([new TeacherGroupAssignmentData(
            assignmentId: 'assignment-1',
            staffProfileId: 'staff-1',
            groupId: 'group-1',
            groupCode: 'G1',
            groupName: ['en' => 'Group 1'],
            groupStatus: 'active',
            courseId: $courseId,
            role: 'primary',
            assignedFrom: now('UTC')->subDay()->toDateString(),
            assignedTo: null,
        )]);

    $scope = new AssessmentManagementScope($staff, $groups);
    $assessmentPolicy = new AssessmentPolicy($scope);
    $attemptPolicy = new AssessmentAttemptPolicy(Mockery::mock(StudentDirectoryQueries::class), $scope);
    $attempt = AssessmentAttempt::factory()->make();
    $attempt->setRelation('assessment', $assessment);

    expect($assessmentPolicy->update($user, $assessment))->toBeTrue()
        ->and($attemptPolicy->grade($user, $attempt))->toBeTrue();

    $assessment->course_id = (string) str()->ulid();

    expect($assessmentPolicy->update($user, $assessment))->toBeFalse()
        ->and($attemptPolicy->grade($user, $attempt))->toBeFalse();
});

it('never inspects role names in policies', function (): void {
    foreach ([AssessmentPolicy::class, AssessmentAttemptPolicy::class] as $policyClass) {
        $source = (string) file_get_contents((new ReflectionClass($policyClass))->getFileName());

        expect(str_contains($source, 'hasRole'))->toBeFalse()
            ->and(str_contains($source, 'role ==='))->toBeFalse();
    }
});

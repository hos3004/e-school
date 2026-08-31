<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Policies\AssessmentAttemptPolicy;
use Modules\Assessments\Application\Policies\AssessmentPolicy;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Tests\Support\ApiUser;
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

it('never inspects role names in policies', function (): void {
    foreach ([AssessmentPolicy::class, AssessmentAttemptPolicy::class] as $policyClass) {
        $source = (string) file_get_contents((new ReflectionClass($policyClass))->getFileName());

        expect(str_contains($source, 'hasRole'))->toBeFalse()
            ->and(str_contains($source, 'role ==='))->toBeFalse();
    }
});

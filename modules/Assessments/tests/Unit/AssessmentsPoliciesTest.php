<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Policies\AssessmentAttemptPolicy;
use Modules\Assessments\Application\Policies\AssessmentPolicy;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Assessments\Tests\Support\ApiUser;

uses(RefreshDatabase::class);

function assessmentsPolicyUser(string $organizationId, bool $granted): ApiUser
{
    Gate::after(fn (): bool => $granted);

    return new ApiUser($organizationId);
}

it('grants assessment abilities only within the same organization', function (): void {
    Gate::after(fn (): bool => true);

    $policy = new AssessmentPolicy;
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

it('scopes attempt abilities to the owning organization', function (): void {
    Gate::after(fn (): bool => true);

    $policy = new AssessmentAttemptPolicy;
    $attempt = AssessmentAttempt::factory()->create([
        'assessment_id' => Assessment::factory()->create()->getKey(),
    ]);

    $owningOrg = $attempt->assessment->organization_id;

    expect($policy->viewAny(assessmentsPolicyUser($owningOrg, false)))->toBeTrue()
        ->and($policy->view(assessmentsPolicyUser($owningOrg, false), $attempt))->toBeTrue()
        ->and($policy->submit(assessmentsPolicyUser($owningOrg, false), $attempt))->toBeTrue()
        ->and($policy->grade(assessmentsPolicyUser($owningOrg, false), $attempt))->toBeTrue()
        // لا حذف للمحاولات إطلاقًا — سجل أكاديمي.
        ->and($policy->delete(assessmentsPolicyUser($owningOrg, true), $attempt))->toBeFalse()
        ->and($policy->grade(assessmentsPolicyUser((string) str()->ulid(), true), $attempt))->toBeFalse();
});

it('never inspects role names in policies', function (): void {
    foreach ([AssessmentPolicy::class, AssessmentAttemptPolicy::class] as $policyClass) {
        $source = (string) file_get_contents((new ReflectionClass($policyClass))->getFileName());

        expect(str_contains($source, 'hasRole'))->toBeFalse()
            ->and(str_contains($source, 'role ==='))->toBeFalse();
    }
});

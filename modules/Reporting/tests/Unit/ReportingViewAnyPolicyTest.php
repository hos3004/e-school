<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Reporting\Application\Policies\StudentDashboardPolicy;
use Modules\Reporting\Application\Policies\TeacherDashboardPolicy;
use Modules\Reporting\Tests\Support\ApiUser;

const REPORTING_POLICY_ORGANIZATION_ID = '01REPORTINGORG000000000000';

it('requires both report and all-students visibility for the student dashboard index', function (): void {
    Gate::define('report.view', static fn (ApiUser $user): bool => $user->getAuthIdentifier() !== 'no-report');
    Gate::define('student.view.any', static fn (ApiUser $user): bool => $user->getAuthIdentifier() === 'allowed');

    $policy = new StudentDashboardPolicy;

    expect($policy->viewAny(new ApiUser('allowed', REPORTING_POLICY_ORGANIZATION_ID)))->toBeTrue()
        ->and($policy->viewAny(new ApiUser('no-students', REPORTING_POLICY_ORGANIZATION_ID)))->toBeFalse()
        ->and($policy->viewAny(new ApiUser('no-report', REPORTING_POLICY_ORGANIZATION_ID)))->toBeFalse();
});

it('requires both report and all-staff visibility for the teacher dashboard index', function (): void {
    Gate::define('report.view', static fn (ApiUser $user): bool => $user->getAuthIdentifier() !== 'no-report');
    Gate::define('staff.view.any', static fn (ApiUser $user): bool => $user->getAuthIdentifier() === 'allowed');

    $policy = new TeacherDashboardPolicy;

    expect($policy->viewAny(new ApiUser('allowed', REPORTING_POLICY_ORGANIZATION_ID)))->toBeTrue()
        ->and($policy->viewAny(new ApiUser('no-staff', REPORTING_POLICY_ORGANIZATION_ID)))->toBeFalse()
        ->and($policy->viewAny(new ApiUser('no-report', REPORTING_POLICY_ORGANIZATION_ID)))->toBeFalse();
});

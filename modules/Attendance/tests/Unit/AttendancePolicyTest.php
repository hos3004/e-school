<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Modules\Attendance\Application\Policies\AttendancePolicy;
use Modules\Attendance\Database\Factories\AttendanceFactory;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Tests\Support\ApiUser;

function attendancePolicyUser(bool $granted): ApiUser
{
    foreach (['view', 'record', 'override'] as $ability) {
        Gate::define("attendance.{$ability}", fn (): bool => $granted);
    }

    return new ApiUser((string) str()->ulid());
}

it('delegates every ability to declared permissions', function (): void {
    $policy = new AttendancePolicy;
    /** @var Attendance $record */
    $record = AttendanceFactory::new()->make();

    expect($policy->viewAny(attendancePolicyUser(true)))->toBeTrue()
        ->and($policy->view(attendancePolicyUser(true), $record))->toBeTrue()
        ->and($policy->create(attendancePolicyUser(true)))->toBeTrue()
        ->and($policy->update(attendancePolicyUser(true), $record))->toBeTrue()
        ->and($policy->delete(attendancePolicyUser(true), $record))->toBeTrue()
        ->and($policy->confirm(attendancePolicyUser(true), $record))->toBeTrue()
        ->and($policy->override(attendancePolicyUser(true), $record))->toBeTrue();

    expect($policy->viewAny(attendancePolicyUser(false)))->toBeFalse()
        ->and($policy->view(attendancePolicyUser(false), $record))->toBeFalse()
        ->and($policy->create(attendancePolicyUser(false)))->toBeFalse()
        ->and($policy->update(attendancePolicyUser(false), $record))->toBeFalse()
        ->and($policy->delete(attendancePolicyUser(false), $record))->toBeFalse()
        ->and($policy->confirm(attendancePolicyUser(false), $record))->toBeFalse()
        ->and($policy->override(attendancePolicyUser(false), $record))->toBeFalse();
});

it('never inspects role names', function (): void {
    $source = (string) file_get_contents((new ReflectionClass(AttendancePolicy::class))->getFileName());

    expect(str_contains($source, 'hasRole'))->toBeFalse()
        ->and(str_contains($source, 'role ==='))->toBeFalse();
});

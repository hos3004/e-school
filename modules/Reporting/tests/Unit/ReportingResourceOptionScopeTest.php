<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Reporting\Domain\Models\TeacherDashboard;
use Modules\Reporting\Presentation\Filament\Resources\StudentDashboardResource;
use Modules\Reporting\Presentation\Filament\Resources\TeacherDashboardResource;
use Modules\Reporting\Tests\Support\ApiUser;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

it('loads student filter options only from the current organization without a stale static cache', function (): void {
    $firstOrganizationId = Fixtures::organizationId();
    $secondOrganizationId = reportingResourceOrganization('student-options');
    $firstStudentId = (string) str()->ulid();
    $secondStudentId = (string) str()->ulid();

    StudentDashboard::query()->create([
        'organization_id' => $firstOrganizationId,
        'enrollment_id' => (string) str()->ulid(),
        'student_profile_id' => $firstStudentId,
    ]);
    StudentDashboard::query()->create([
        'organization_id' => $secondOrganizationId,
        'enrollment_id' => (string) str()->ulid(),
        'student_profile_id' => $secondStudentId,
    ]);

    $method = new ReflectionMethod(StudentDashboardResource::class, 'studentNames');

    $this->actingAs(new ApiUser('first', $firstOrganizationId));
    $this->mock(StudentDirectoryQueries::class, function (MockInterface $mock) use ($firstOrganizationId, $firstStudentId): void {
        $mock->shouldReceive('namesForProfiles')
            ->once()
            ->with($firstOrganizationId, [$firstStudentId])
            ->andReturn([$firstStudentId => 'First student']);
    });

    expect($method->invoke(null))->toBe([$firstStudentId => 'First student']);

    $this->actingAs(new ApiUser('second', $secondOrganizationId));
    $this->mock(StudentDirectoryQueries::class, function (MockInterface $mock) use ($secondOrganizationId, $secondStudentId): void {
        $mock->shouldReceive('namesForProfiles')
            ->once()
            ->with($secondOrganizationId, [$secondStudentId])
            ->andReturn([$secondStudentId => 'Second student']);
    });

    expect($method->invoke(null))->toBe([$secondStudentId => 'Second student']);
});

it('loads teacher filter options only from the current organization without a stale static cache', function (): void {
    $firstOrganizationId = Fixtures::organizationId();
    $secondOrganizationId = reportingResourceOrganization('teacher-options');
    $firstStaffId = (string) str()->ulid();
    $secondStaffId = (string) str()->ulid();

    TeacherDashboard::query()->create([
        'organization_id' => $firstOrganizationId,
        'staff_profile_id' => $firstStaffId,
    ]);
    TeacherDashboard::query()->create([
        'organization_id' => $secondOrganizationId,
        'staff_profile_id' => $secondStaffId,
    ]);

    $method = new ReflectionMethod(TeacherDashboardResource::class, 'teacherNames');

    $this->actingAs(new ApiUser('first', $firstOrganizationId));
    $this->mock(StaffQueries::class, function (MockInterface $mock) use ($firstOrganizationId, $firstStaffId): void {
        $mock->shouldReceive('namesForProfiles')
            ->once()
            ->with($firstOrganizationId, [$firstStaffId])
            ->andReturn([$firstStaffId => 'First teacher']);
    });

    expect($method->invoke(null))->toBe([$firstStaffId => 'First teacher']);

    $this->actingAs(new ApiUser('second', $secondOrganizationId));
    $this->mock(StaffQueries::class, function (MockInterface $mock) use ($secondOrganizationId, $secondStaffId): void {
        $mock->shouldReceive('namesForProfiles')
            ->once()
            ->with($secondOrganizationId, [$secondStaffId])
            ->andReturn([$secondStaffId => 'Second teacher']);
    });

    expect($method->invoke(null))->toBe([$secondStaffId => 'Second teacher']);
});

function reportingResourceOrganization(string $suffix): string
{
    $id = (string) str()->ulid();

    DB::table('organizations')->insert([
        'id' => $id,
        'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Other organization'], JSON_UNESCAPED_UNICODE),
        'slug' => $suffix.'-'.strtolower(substr($id, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

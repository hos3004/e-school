<?php

declare(strict_types=1);

namespace Modules\Reporting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Reporting\Domain\Events\OrganizationSnapshotRecorded;
use Modules\Reporting\Domain\Events\StudentDashboardUpdated;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Reporting\Tests\Support\ApiUser;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

const ACTOR_ID = '01REPORTINGACTOR0000000000';

function reportingActor(): ApiUser
{
    return new ApiUser(ACTOR_ID, Fixtures::organizationId());
}

it('builds a snapshot through the api and returns 201', function (): void {
    Event::fake([OrganizationSnapshotRecorded::class]);
    Gate::define('reporting.snapshot.build', fn (): bool => true);

    $response = $this->actingAs(reportingActor())
        ->postJson('/api/organization-snapshots', [
            'organization_id' => Fixtures::organizationId(),
            'snapshot_date' => '2026-08-22',
            'students_active' => 42,
            'students_frozen' => 3,
            'teachers_active' => 9,
            'sessions_held' => 55,
            'sessions_cancelled' => 6,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.students_active', 42)
        ->assertJsonPath('data.period_type.value', 'daily');

    expect(OrganizationSnapshot::query()->count())->toBe(1);
    Event::assertDispatched(OrganizationSnapshotRecorded::class);
});

it('rejects a snapshot build without the permission', function (): void {
    Gate::define('reporting.snapshot.build', fn (): bool => false);

    $this->actingAs(reportingActor())
        ->postJson('/api/organization-snapshots', [
            'organization_id' => Fixtures::organizationId(),
            'snapshot_date' => '2026-08-22',
            'students_active' => 1,
            'students_frozen' => 0,
            'teachers_active' => 1,
            'sessions_held' => 2,
            'sessions_cancelled' => 0,
        ])->assertForbidden();
});

it('ignores a client supplied organization when building a snapshot', function (): void {
    Event::fake([OrganizationSnapshotRecorded::class]);
    Gate::define('reporting.snapshot.build', fn (): bool => true);

    $actorOrganizationId = Fixtures::organizationId();
    $foreignOrganizationId = (string) str()->ulid();

    DB::table('organizations')->insert([
        'id' => $foreignOrganizationId,
        'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Other organization'], JSON_UNESCAPED_UNICODE),
        'slug' => 'reporting-foreign-'.strtolower(substr($foreignOrganizationId, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(reportingActor())
        ->postJson('/api/organization-snapshots', [
            'organization_id' => $foreignOrganizationId,
            'snapshot_date' => '2026-08-23',
            'students_active' => 5,
            'students_frozen' => 0,
            'teachers_active' => 2,
            'sessions_held' => 3,
            'sessions_cancelled' => 1,
        ])->assertCreated();

    expect(OrganizationSnapshot::query()
        ->where('organization_id', $actorOrganizationId)
        ->whereDate('snapshot_date', '2026-08-23')
        ->exists())->toBeTrue()
        ->and(OrganizationSnapshot::query()
            ->where('organization_id', $foreignOrganizationId)
            ->whereDate('snapshot_date', '2026-08-23')
            ->exists())->toBeFalse();
});

it('validates snapshot payload bounds', function (): void {
    Gate::define('reporting.snapshot.build', fn (): bool => true);

    $this->actingAs(reportingActor())
        ->postJson('/api/organization-snapshots', [
            'organization_id' => Fixtures::organizationId(),
            'snapshot_date' => 'not-a-date',
            'students_active' => -5,
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['snapshot_date', 'students_active']);
});

it('returns a student dashboard by enrollment', function (): void {
    Gate::define('report.view', fn (): bool => true);
    Gate::define('student.view.any', fn (): bool => true);

    $dashboard = StudentDashboard::factory()->create();

    $this->actingAs(reportingActor())
        ->getJson('/api/student-dashboards/'.(string) $dashboard->enrollment_id)
        ->assertOk()
        ->assertJsonPath('enrollment_id', (string) $dashboard->enrollment_id)
        ->assertJsonPath('attendance_rate_bp', (int) $dashboard->attendance_rate_bp);
});

it('returns 404 for a dashboard that was never projected', function (): void {
    Gate::define('report.view', fn (): bool => true);
    Gate::define('student.view.any', fn (): bool => true);

    $this->actingAs(reportingActor())
        ->getJson('/api/student-dashboards/01UNKNOWNENROLLMENT00000')
        ->assertNotFound();
});

it('corrects a counter with a documented reason through the api', function (): void {
    Event::fake([StudentDashboardUpdated::class]);
    Gate::define('reporting.student.correct', fn (): bool => true);

    $dashboard = StudentDashboard::factory()->create([
        'sessions_attended' => 3,
        'sessions_missed' => 1,
    ]);

    $this->actingAs(reportingActor())
        ->postJson('/api/student-dashboards/corrections', [
            'enrollment_id' => (string) $dashboard->enrollment_id,
            'column' => 'sessions_attended',
            'value' => 7,
            'reason' => 'تصحيح بعد تدقيق سجل الحضور الأسبوعي',
        ])->assertOk()
        ->assertJsonPath('data.sessions_attended', 7)
        ->assertJsonPath('data.attendance_rate_bp', 8750);

    expect($dashboard->fresh()->sessions_attended)->toBe(7);
});

it('rejects corrections with an undocumented or short reason', function (): void {
    Gate::define('reporting.student.correct', fn (): bool => true);

    $dashboard = StudentDashboard::factory()->create();

    $this->actingAs(reportingActor())
        ->postJson('/api/student-dashboards/corrections', [
            'enrollment_id' => (string) $dashboard->enrollment_id,
            'column' => 'sessions_attended',
            'value' => 7,
            'reason' => 'لا',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('rejects corrections without the permission', function (): void {
    Gate::define('reporting.student.correct', fn (): bool => false);

    $dashboard = StudentDashboard::factory()->create();

    $this->actingAs(reportingActor())
        ->postJson('/api/student-dashboards/corrections', [
            'enrollment_id' => (string) $dashboard->enrollment_id,
            'column' => 'sessions_attended',
            'value' => 7,
            'reason' => 'تصحيح بعد تدقيق سجل الحضور الأسبوعي',
        ])->assertForbidden();
});

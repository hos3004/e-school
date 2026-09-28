<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    Gate::define('payroll.view', fn (): bool => true);
});

function createTeacherContract(string $organizationId, string $staffProfileId): string
{
    $contractId = (string) Str::ulid();

    DB::table('teacher_contracts')->insert([
        'id' => $contractId,
        'organization_id' => $organizationId,
        'staff_profile_id' => $staffProfileId,
        'basis' => 'per_session',
        'effective_from' => CarbonImmutable::now('UTC')->subMonths(6)->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $contractId;
}

function createPayrollPeriod(string $organizationId, int $year, int $month, string $status = 'approved'): string
{
    $periodId = (string) Str::ulid();

    DB::table('payroll_periods')->insert([
        'id' => $periodId,
        'organization_id' => $organizationId,
        'year' => $year,
        'month' => $month,
        'starts_on' => CarbonImmutable::create($year, $month, 1)->toDateString(),
        'ends_on' => CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString(),
        'status' => $status,
        'totals' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $periodId;
}

function createPayrollEntry(
    string $organizationId,
    string $periodId,
    string $staffProfileId,
    string $contractId,
    int $amount,
): string {
    $entryId = (string) Str::ulid();

    DB::table('payroll_entries')->insert([
        'id' => $entryId,
        'organization_id' => $organizationId,
        'payroll_period_id' => $periodId,
        'staff_profile_id' => $staffProfileId,
        'session_id' => null,
        'teacher_contract_id' => $contractId,
        'entry_type' => 'session_completed',
        'outcome_key' => 'completed',
        'amount' => $amount,
        'currency' => 'EGP',
        'rate_snapshot' => json_encode(['amount' => $amount]),
        'status' => 'recorded',
        'created_at' => now(),
    ]);

    return $entryId;
}

it('lists earnings periods with entries for the authenticated teacher', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $contractId = createTeacherContract($organizationId, $staffProfileId);

    $periodId = createPayrollPeriod($organizationId, 2026, 9);
    createPayrollEntry($organizationId, $periodId, $staffProfileId, $contractId, 15000);
    createPayrollEntry($organizationId, $periodId, $staffProfileId, $contractId, 12000);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/earnings');

    $response->assertOk()->assertJsonPath('has_profile', true);
    $periods = $response->json('periods');
    expect($periods)->toHaveCount(1)
        ->and($periods[0]['id'])->toBe($periodId)
        ->and($periods[0]['earningsMinorUnits'])->toBe(27000)
        ->and($periods[0]['currency'])->toBe('EGP')
        ->and($periods[0]['entries'])->toHaveCount(2);
});

it('returns an empty period list for a teacher with no payroll entries yet', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/earnings');

    $response->assertOk()
        ->assertJsonPath('has_profile', true)
        ->assertJsonPath('periods', []);
});

it('returns has_profile false with an empty list for a teacher with no staff profile', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();

    $response = $this->actingAs($teacher)->getJson('/api/teacher/earnings');

    $response->assertOk()
        ->assertJsonPath('has_profile', false)
        ->assertJsonPath('periods', []);
});

it('hides earnings entirely for a teacher whose financial visibility is off', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    DB::table('staff_profiles')->where('id', $staffProfileId)->update(['financials_visible' => false]);

    $this->actingAs($teacher)
        ->getJson('/api/teacher/earnings')
        ->assertForbidden();
});

it('never leaks another teachers payroll entries', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());
    $otherContractId = createTeacherContract($organizationId, $otherStaffProfileId);
    $periodId = createPayrollPeriod($organizationId, 2026, 9);
    createPayrollEntry($organizationId, $periodId, $otherStaffProfileId, $otherContractId, 20000);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/earnings');

    $response->assertOk()->assertJsonPath('periods', []);
});

it('requires authentication', function (): void {
    $this->getJson('/api/teacher/earnings')->assertUnauthorized();
});

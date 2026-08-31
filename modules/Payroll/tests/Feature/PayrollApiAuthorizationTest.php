<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Payroll\Application\Actions\ProposePayrollAdjustmentAction;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\Money;

uses(RefreshDatabase::class);

it('prevents cross tenant payroll reads and decisions while auditing a same tenant approval', function (): void {
    $first = payrollSecurityFixture(8);
    $second = payrollSecurityFixture(9);
    $auditor = User::factory()->inOrganization((string) $first['organization']->id)->create();

    $this->seed(AccessControlSeeder::class);
    payrollAssignSeededRole($first['approver'], 'finance_supervisor');
    payrollAssignSeededRole($second['approver'], 'finance_supervisor');
    payrollAssignSeededRole($auditor, 'auditor');
    app(PermissionGateRegistrar::class)->register();

    $this->actingAs($first['approver'])
        ->getJson('/api/payroll/periods/'.$first['period']->id)
        ->assertOk();
    $this->actingAs($first['approver'])
        ->getJson('/api/payroll/periods')
        ->assertOk();
    $this->actingAs($auditor)
        ->getJson('/api/payroll/entries')
        ->assertOk();
    $this->actingAs($auditor)
        ->postJson('/api/payroll/adjustments/'.$first['adjustment']->id.'/approve', [
            'reason' => 'auditor must remain read only',
        ])
        ->assertForbidden();
    $this->actingAs($second['approver'])
        ->getJson('/api/payroll/periods/'.$first['period']->id)
        ->assertNotFound();
    $this->actingAs($second['approver'])
        ->postJson('/api/payroll/adjustments/'.$first['adjustment']->id.'/approve', [
            'reason' => 'foreign tenant must not decide',
        ])
        ->assertNotFound();
    expect($first['adjustment']->refresh()->approved_at)->toBeNull();

    $this->actingAs($first['approver'])
        ->postJson('/api/payroll/adjustments/'.$first['adjustment']->id.'/approve', [
            'reason' => 'reviewed supporting payroll evidence',
        ])
        ->assertOk();
    expect($first['adjustment']->refresh()->approved_by)->toBe((string) $first['approver']->id)
        ->and(AuditLog::query()
            ->where('organization_id', $first['organization']->id)
            ->where('actor_id', $first['approver']->id)
            ->where('action', 'payroll.adjustment.approved')
            ->whereNotNull('reason')
            ->exists())->toBeTrue();
});

it('validates payroll staff and reference periods in the actor organization inside the transaction', function (): void {
    $first = payrollSecurityFixture(10);
    $second = payrollSecurityFixture(11);
    $action = app(ProposePayrollAdjustmentAction::class);
    $money = Money::fromMajor('25.00', (string) config('payroll.currency'));

    expect(fn () => $action->execute(
        organizationId: (string) $first['organization']->id,
        payrollPeriodId: (string) $first['period']->id,
        staffProfileId: (string) $second['staff']->id,
        type: 'bonus',
        amount: $money,
        reason: 'foreign staff must be rejected',
        actorId: (string) $first['proposer']->id,
    ))->toThrow(BusinessRuleViolation::class);

    expect(fn () => $action->execute(
        organizationId: (string) $first['organization']->id,
        payrollPeriodId: (string) $first['period']->id,
        staffProfileId: (string) $first['staff']->id,
        type: 'bonus',
        amount: $money,
        reason: 'foreign reference must be rejected',
        referencesPeriodId: (string) $second['period']->id,
        actorId: (string) $first['proposer']->id,
    ))->toThrow(BusinessRuleViolation::class);

    $adjustment = $action->execute(
        organizationId: (string) $first['organization']->id,
        payrollPeriodId: (string) $first['period']->id,
        staffProfileId: (string) $first['staff']->id,
        type: 'bonus',
        amount: $money,
        reason: 'same tenant evidence verified',
        referencesPeriodId: (string) $first['referencePeriod']->id,
        actorId: (string) $first['proposer']->id,
    );

    expect($adjustment->organization_id)->toBe((string) $first['organization']->id)
        ->and(AuditLog::query()
            ->where('auditable_id', $adjustment->id)
            ->where('action', 'payroll.adjustment.proposed')
            ->whereNotNull('reason')
            ->exists())->toBeTrue();
});

/** @return array<string, object> */
function payrollSecurityFixture(int $month): array
{
    $organization = Organization::factory()->create();
    $proposer = User::factory()->inOrganization((string) $organization->id)->create();
    $approver = User::factory()->inOrganization((string) $organization->id)->create();
    $staffUser = User::factory()->inOrganization((string) $organization->id)->create();
    $staff = StaffProfile::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $staffUser->id,
        'staff_code' => 'PAY-'.$month,
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Male,
        'hired_at' => '2026-01-01',
    ]);
    $period = PayrollPeriod::query()->create([
        'organization_id' => $organization->id,
        'year' => 2026,
        'month' => $month,
        'starts_on' => sprintf('2026-%02d-01', $month),
        'ends_on' => sprintf('2026-%02d-28', $month),
        'status' => PayrollPeriodStatus::Open,
        'totals' => [],
    ]);
    $referenceMonth = $month === 1 ? 12 : $month - 1;
    $referencePeriod = PayrollPeriod::query()->create([
        'organization_id' => $organization->id,
        'year' => $month === 1 ? 2025 : 2026,
        'month' => $referenceMonth,
        'starts_on' => sprintf('%d-%02d-01', $month === 1 ? 2025 : 2026, $referenceMonth),
        'ends_on' => sprintf('%d-%02d-28', $month === 1 ? 2025 : 2026, $referenceMonth),
        'status' => PayrollPeriodStatus::Open,
        'totals' => [],
    ]);
    $adjustment = PayrollAdjustment::query()->create([
        'organization_id' => $organization->id,
        'payroll_period_id' => $period->id,
        'staff_profile_id' => $staff->id,
        'type' => 'bonus',
        'amount' => 2500,
        'currency' => config('payroll.currency'),
        'reason' => 'fixture proposal',
        'references_period_id' => null,
        'proposed_by' => $proposer->id,
        'proposed_at' => now('UTC'),
    ]);

    return compact(
        'organization',
        'proposer',
        'approver',
        'staff',
        'period',
        'referencePeriod',
        'adjustment',
    );
}

function payrollAssignSeededRole(User $user, string $roleName): void
{
    $role = Role::query()
        ->includingGlobal((string) $user->organization_id)
        ->where('name', $roleName)
        ->firstOrFail();

    ModelHasRole::query()->create([
        'role_id' => $role->id,
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->id,
    ]);
}

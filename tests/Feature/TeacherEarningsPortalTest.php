<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Domain\Models\User;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Tests\TestCase;

/**
 * ADR-017 — كشف أجر المعلم في بوابته.
 *
 * يحرس ثلاثة أشياء: أن الكشف يُبنى من الدفتر لا من مجموع مخزَّن، وأن معلمًا
 * لا يرى قيود زميله، وأن التسوية المقترحة لا تظهر قبل اعتمادها.
 */
final class TeacherEarningsPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_statement_is_built_from_the_ledger(): void
    {
        Gate::define('payroll.view', static fn (): bool => true);

        $context = $this->ledgerContext();

        $response = $this->actingAs($context['user'])->get('/teacher/earnings');

        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Teacher/Earnings')
                ->where('hasProfile', true)
                ->has('periods', 1)
                // 5000 مستحق − 1500 خصم + 2000 مكافأة = 5500
                ->where('periods.0.earningsMinorUnits', 5_000)
                ->where('periods.0.deductionsMinorUnits', -1_500)
                ->where('periods.0.adjustmentsMinorUnits', 2_000)
                ->where('periods.0.netMinorUnits', 5_500)
                ->where('periods.0.sessionsCount', 2));
    }

    public function test_a_teacher_never_sees_another_teachers_entries(): void
    {
        Gate::define('payroll.view', static fn (): bool => true);

        $context = $this->ledgerContext();

        $otherStaffProfileId = $this->staffProfile(
            $context['organization_id'],
            $this->user($context['organization_id']),
        );

        $this->entry(
            $context['organization_id'],
            $context['period_id'],
            $otherStaffProfileId,
            $context['contract_id'],
            99_000,
        );

        $response = $this->actingAs($context['user'])->get('/teacher/earnings');

        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('periods.0.earningsMinorUnits', 5_000));
    }

    public function test_an_unapproved_adjustment_is_not_shown(): void
    {
        Gate::define('payroll.view', static fn (): bool => true);

        $context = $this->ledgerContext();

        // تسوية مقترحة لم يعتمدها أحد بعد.
        DB::table('payroll_adjustments')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $context['organization_id'],
            'payroll_period_id' => $context['period_id'],
            'staff_profile_id' => $context['staff_profile_id'],
            'type' => 'bonus',
            'amount' => 50_000,
            'currency' => 'EGP',
            'reason' => 'مقترحة ولم تُعتمد',
            'proposed_by' => (string) $context['user']->getKey(),
            'proposed_at' => now(),
            'created_at' => now(),
        ]);

        $this->actingAs($context['user'])
            ->get('/teacher/earnings')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('periods.0.adjustmentsMinorUnits', 2_000)
                ->where('periods.0.netMinorUnits', 5_500));
    }

    public function test_the_route_requires_the_payroll_permission(): void
    {
        Gate::define('payroll.view', static fn (): bool => false);

        $context = $this->ledgerContext();

        $this->actingAs($context['user'])
            ->get('/teacher/earnings')
            ->assertForbidden();
    }

    /**
     * فترة مفتوحة فيها: قيدتا اكتساب وخصم لحصتين، ومكافأة معتمدة.
     *
     * @return array<string, mixed>
     */
    private function ledgerContext(): array
    {
        $organizationId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'earnings-'.strtolower((string) Str::ulid()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = $this->user($organizationId);
        $staffProfileId = $this->staffProfile($organizationId, $user);

        $now = CarbonImmutable::now('UTC');

        $contractId = (string) Str::ulid();
        DB::table('teacher_contracts')->insert([
            'id' => $contractId,
            'organization_id' => $organizationId,
            'staff_profile_id' => $staffProfileId,
            'basis' => 'per_session',
            'effective_from' => $now->subMonth()->toDateString(),
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $periodId = (string) Str::ulid();
        DB::table('payroll_periods')->insert([
            'id' => $periodId,
            'organization_id' => $organizationId,
            'year' => (int) $now->year,
            'month' => (int) $now->month,
            'starts_on' => $now->startOfMonth()->toDateString(),
            'ends_on' => $now->endOfMonth()->toDateString(),
            'status' => PayrollPeriodStatus::Open->value,
            'totals' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->entry($organizationId, $periodId, $staffProfileId, $contractId, 5_000);
        $this->entry(
            $organizationId,
            $periodId,
            $staffProfileId,
            $contractId,
            -1_500,
            entryType: 'session_deduction',
            outcomeKey: 'teacher_absent',
        );

        DB::table('payroll_adjustments')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'payroll_period_id' => $periodId,
            'staff_profile_id' => $staffProfileId,
            'type' => 'bonus',
            'amount' => 2_000,
            'currency' => 'EGP',
            'reason' => 'تميّز في المتابعة',
            'proposed_by' => (string) $user->getKey(),
            'proposed_at' => now(),
            'approved_by' => (string) $this->user($organizationId)->getKey(),
            'approved_at' => now(),
            'created_at' => now(),
        ]);

        return [
            'organization_id' => $organizationId,
            'user' => $user,
            'staff_profile_id' => $staffProfileId,
            'contract_id' => $contractId,
            'period_id' => $periodId,
        ];
    }

    private function entry(
        string $organizationId,
        string $periodId,
        string $staffProfileId,
        string $contractId,
        int $amount,
        string $entryType = 'session_earning',
        string $outcomeKey = 'completed',
    ): void {
        DB::table('payroll_entries')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'payroll_period_id' => $periodId,
            'staff_profile_id' => $staffProfileId,
            'session_id' => (string) Str::ulid(),
            'teacher_contract_id' => $contractId,
            'entry_type' => $entryType,
            'outcome_key' => $outcomeKey,
            'amount' => $amount,
            'currency' => 'EGP',
            'rate_snapshot' => json_encode(
                ['amount_minor_units' => $amount, 'currency' => 'EGP'],
                JSON_THROW_ON_ERROR,
            ),
            'status' => PayrollEntryStatus::Recorded->value,
            'created_at' => now(),
        ]);
    }

    private function user(string $organizationId): User
    {
        return User::factory()->inOrganization($organizationId)->create([
            'email' => Str::lower(Str::random(12)).'@example.test',
        ]);
    }

    private function staffProfile(string $organizationId, User $user): string
    {
        $staffProfileId = (string) Str::ulid();

        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId,
            'organization_id' => $organizationId,
            'user_id' => (string) $user->getKey(),
            'staff_code' => 'EARN-'.Str::upper(Str::random(8)),
            'employment_type' => 'part_time',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $staffProfileId;
    }
}

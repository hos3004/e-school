<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Infrastructure\Persistence\DbTeacherRateResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TeacherSalaryDeductionRateTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('salarySessionValues')]
    public function test_salary_deductions_round_minor_units_without_losing_integer_precision(
        int $baseAmount,
        int $monthlyTargetSessions,
        int $expectedMinorUnits,
    ): void {
        $organization = Organization::factory()->create();
        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $profile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $teacher->id,
            'staff_code' => 'SAL-'.str()->random(8),
            'employment_type' => EmploymentType::Contractor,
        ]);
        $sessionDate = CarbonImmutable::parse('2026-09-06 10:00:00 UTC');
        $contract = TeacherContract::query()->create([
            'organization_id' => (string) $organization->id,
            'staff_profile_id' => (string) $profile->id,
            'basis' => ContractBasis::Salary,
            'effective_from' => $sessionDate->subMonth()->toDateString(),
            'base_amount' => $baseAmount,
            'currency' => 'EGP',
            'monthly_target_sessions' => $monthlyTargetSessions,
        ]);

        $rate = app(DbTeacherRateResolver::class)->resolveDeduction(
            (string) $profile->id,
            $sessionDate,
        );

        self::assertNotNull($rate);
        self::assertSame($expectedMinorUnits, $rate['money']->minorUnits);
        self::assertSame('EGP', $rate['money']->currency);
        self::assertSame((string) $contract->id, $rate['contract_id']);
    }

    /** @return array<string, array{int, int, int}> */
    public static function salarySessionValues(): array
    {
        return [
            'exact division' => [60_000, 12, 5_000],
            'below half a minor unit' => [100, 3, 33],
            'above half a minor unit' => [101, 3, 34],
            'exactly half a minor unit' => [101, 2, 51],
            'zero base amount' => [0, 12, 0],
            'beyond float integer precision' => [9_007_199_254_740_993, 2, 4_503_599_627_370_497],
            'maximum bigint remains exact' => [PHP_INT_MAX, 1, PHP_INT_MAX],
        ];
    }
}

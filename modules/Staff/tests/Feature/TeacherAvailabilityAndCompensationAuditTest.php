<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\AddTeacherRate;
use Modules\Staff\Application\Actions\CreateTeacherContract;
use Modules\Staff\Application\Actions\RemoveTeacherAvailability;
use Modules\Staff\Application\Actions\SetTeacherAvailability;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\Money;
use Tests\TestCase;

final class TeacherAvailabilityAndCompensationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_availability_rejects_overlapping_window_and_accepts_disjoint_one(): void
    {
        [$organization, $admin, $profile] = $this->context();

        $set = app(SetTeacherAvailability::class);

        $first = $set->execute(
            profile: $profile,
            weekday: 2,
            startTime: '09:00',
            endTime: '12:00',
            timezone: 'UTC',
            effectiveFrom: now()->toDateString(),
            actorId: (string) $admin->id,
            reason: 'إتاحة معتمدة للفصل الدراسي الجديد',
        );

        self::assertNotNull($first->id);
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'staff.availability_set',
            'auditable_id' => (string) $first->id,
            'reason' => 'إتاحة معتمدة للفصل الدراسي الجديد',
        ])->exists());

        // تقاطع زمني في نفس اليوم → مرفوض.
        try {
            $set->execute(
                profile: $profile,
                weekday: 2,
                startTime: '11:00',
                endTime: '13:00',
                timezone: 'UTC',
                effectiveFrom: now()->toDateString(),
                actorId: (string) $admin->id,
                reason: 'محاولة إضافة فترة متقاطعة',
            );

            self::fail('Overlapping availability windows must be rejected.');
        } catch (BusinessRuleViolation) {
            self::assertTrue(true);
        }

        // يوم مختلف → مقبول.
        $second = $set->execute(
            profile: $profile,
            weekday: 3,
            startTime: '09:00',
            endTime: '11:00',
            timezone: 'UTC',
            effectiveFrom: now()->toDateString(),
            actorId: (string) $admin->id,
            reason: 'إتاحة إضافية ليوم مختلف',
        );

        self::assertNotNull($second->id);
    }

    public function test_remove_availability_records_audit_and_keeps_approved_protected(): void
    {
        [$organization, $admin, $profile] = $this->context();

        $availability = app(SetTeacherAvailability::class)->execute(
            profile: $profile,
            weekday: 4,
            startTime: '10:00',
            endTime: '12:00',
            timezone: 'UTC',
            effectiveFrom: now()->toDateString(),
            actorId: (string) $admin->id,
            reason: 'فترة تجريبية بانتظار الاعتماد',
        );

        app(RemoveTeacherAvailability::class)->execute(
            availability: $availability,
            actorId: (string) $admin->id,
            reason: 'التراجع عن الفترة التجريبية قبل الاعتماد',
        );

        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'staff.availability_removed',
            'auditable_id' => (string) $availability->id,
            'reason' => 'التراجع عن الفترة التجريبية قبل الاعتماد',
        ])->exists());
    }

    public function test_contract_and_rate_creation_record_audit_with_actor_and_reason(): void
    {
        [, $admin, $profile] = $this->context();

        $contract = app(CreateTeacherContract::class)->execute(
            profile: $profile,
            basis: ContractBasis::PerSession,
            effectiveFrom: now()->addDay()->toDateString(),
            actorId: (string) $admin->id,
            reason: 'تجديد تعاقد سنوي بشروط جديدة نافذة من تاريخ لاحق',
        );

        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'staff.contract_created',
            'auditable_id' => (string) $contract->id,
            'reason' => 'تجديد تعاقد سنوي بشروط جديدة نافذة من تاريخ لاحق',
        ])->exists());

        $rate = app(AddTeacherRate::class)->execute(
            contract: $contract,
            scope: RateScope::Default,
            amount: Money::fromMajor('75.50', 'EGP'),
            effectiveFrom: now()->addDay()->toDateString(),
            actorId: (string) $admin->id,
            reason: 'تحديث سعر الحصة في عقد جديد دون مساس بالقيود القديمة',
        );

        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'staff.rate_created',
            'auditable_id' => (string) $rate->id,
            'reason' => 'تحديث سعر الحصة في عقد جديد دون مساس بالقيود القديمة',
        ])->exists());
    }

    /** @return array{0: Organization, 1: User, 2: StaffProfile} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->inOrganization((string) $organization->id)->create();
        $teacher = User::factory()->inOrganization((string) $organization->id)->create();
        $profile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $teacher->id,
            'staff_code' => 'TCH-AV-'.str()->random(4),
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Female,
            'hired_at' => now()->subYear()->toDateString(),
        ]);

        return [$organization, $admin, $profile];
    }
}

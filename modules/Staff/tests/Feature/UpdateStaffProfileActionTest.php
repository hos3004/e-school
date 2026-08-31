<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\UpdateStaffProfileAction;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class UpdateStaffProfileActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GeographySeeder::class);
    }

    public function test_updates_editable_fields_and_records_audit_with_reason(): void
    {
        [$organization, $admin, $profile] = $this->context();
        [$countryId, $regionId] = $this->geographyIds();

        app(UpdateStaffProfileAction::class)->execute(
            profile: $profile,
            changes: [
                'phone' => '+20100111222',
                'specializations' => ['رياضيات', 'فيزياء'],
                'country_id' => $countryId,
                'region_id' => $regionId,
            ],
            actorId: (string) $admin->id,
            reason: 'تحديث بيانات الاتصال والتخصصات بعد المراجعة الدورية',
        );

        $profile->refresh();

        self::assertSame('+20100111222', $profile->phone);
        self::assertSame(['رياضيات', 'فيزياء'], $profile->specializations);

        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'staff.profile_updated',
            'auditable_id' => (string) $profile->id,
            'reason' => 'تحديث بيانات الاتصال والتخصصات بعد المراجعة الدورية',
        ])->exists());
    }

    public function test_ignores_ownership_fields_even_when_submitted(): void
    {
        [$organization, $admin, $profile] = $this->context();
        $otherOrg = Organization::factory()->create();

        app(UpdateStaffProfileAction::class)->execute(
            profile: $profile,
            changes: [
                'organization_id' => (string) $otherOrg->id,
                'user_id' => (string) $admin->id,
                'terminated_at' => now()->toDateString(),
            ],
            actorId: (string) $admin->id,
            reason: 'محاولة تعديل حقول الملكية من النموذج العام',
        );

        $profile->refresh();

        self::assertSame((string) $organization->id, (string) $profile->organization_id);
        self::assertNull($profile->terminated_at);
    }

    public function test_rejects_duplicate_staff_code(): void
    {
        [, $admin, $profile] = $this->context();

        $otherOrg = Organization::factory()->create();
        StaffProfile::query()->create([
            'organization_id' => (string) $otherOrg->id,
            'user_id' => User::factory()->inOrganization((string) $otherOrg->id)->create()->id,
            'staff_code' => 'TCH-DUP-2',
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Male,
            'hired_at' => now()->toDateString(),
        ]);

        $this->expectException(BusinessRuleViolation::class);

        app(UpdateStaffProfileAction::class)->execute(
            profile: $profile,
            changes: ['staff_code' => 'TCH-DUP-2'],
            actorId: (string) $admin->id,
            reason: 'محاولة استخدام رقم وظيفي مستخدم',
        );
    }

    public function test_rejects_region_outside_country(): void
    {
        [, $admin, $profile] = $this->context();
        [$countryId] = $this->geographyIds();

        $this->expectException(BusinessRuleViolation::class);

        app(UpdateStaffProfileAction::class)->execute(
            profile: $profile,
            changes: [
                'country_id' => $countryId,
                'region_id' => (string) str()->ulid(),
            ],
            actorId: (string) $admin->id,
            reason: 'جغرافيا غير متسقة',
        );
    }

    public function test_rejects_actor_from_another_organization(): void
    {
        [, , $profile] = $this->context();
        $outsider = User::factory()->inOrganization((string) Organization::factory()->create()->id)->create();

        $this->expectException(BusinessRuleViolation::class);

        app(UpdateStaffProfileAction::class)->execute(
            profile: $profile,
            changes: ['phone' => '+20123456789'],
            actorId: (string) $outsider->id,
            reason: 'محاولة عبور حدود المؤسسة',
        );
    }

    public function test_requires_written_reason(): void
    {
        [, $admin, $profile] = $this->context();

        $this->expectException(BusinessRuleViolation::class);

        app(UpdateStaffProfileAction::class)->execute(
            profile: $profile,
            changes: ['phone' => '+20123456788'],
            actorId: (string) $admin->id,
            reason: '',
        );
    }

    /**
     * النموذج يعيد إرسال كل الحقول حتى غير المعدَّلة. الحقول المحوَّلة
     * (enum/تاريخ/مصفوفة) يجب أن تُقارَن بعد التحويل، وإلا سجّلنا في
     * دفتر التدقيق تغييرًا لم يحدث.
     */
    public function test_resubmitting_unchanged_cast_fields_records_no_audit_entry(): void
    {
        [, $admin, $profile] = $this->context();

        $before = DB::table('audit_log')->where('auditable_id', (string) $profile->id)->count();

        app(UpdateStaffProfileAction::class)->execute(
            profile: $profile,
            changes: [
                'staff_code' => (string) $profile->staff_code,
                'gender' => StaffGender::Female->value,
                'employment_type' => EmploymentType::Contractor->value,
            ],
            actorId: (string) $admin->id,
            reason: 'إعادة حفظ النموذج بلا تغيير فعلي',
        );

        self::assertSame(
            $before,
            DB::table('audit_log')->where('auditable_id', (string) $profile->id)->count(),
        );
    }

    /** التدقيق يخزّن قيمة نصية بدائية للـ enum قبل وبعد، لا كائنًا. */
    public function test_audit_stores_primitive_values_for_enum_fields(): void
    {
        [, $admin, $profile] = $this->context();

        app(UpdateStaffProfileAction::class)->execute(
            profile: $profile,
            changes: ['gender' => StaffGender::Male->value],
            actorId: (string) $admin->id,
            reason: 'تصحيح النوع بعد مراجعة المستندات',
        );

        /** @var object{old_values: string|null, new_values: string|null}|null $entry */
        $entry = DB::table('audit_log')
            ->where('action', 'staff.profile_updated')
            ->where('auditable_id', (string) $profile->id)
            ->latest('created_at')
            ->first();

        self::assertNotNull($entry);
        self::assertSame(
            StaffGender::Female->value,
            json_decode((string) $entry->old_values, true)['gender'] ?? null,
        );
        self::assertSame(
            StaffGender::Male->value,
            json_decode((string) $entry->new_values, true)['gender'] ?? null,
        );
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
            'staff_code' => 'TCH-UP-'.str()->random(4),
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Female,
            'hired_at' => now()->subYear()->toDateString(),
        ]);

        return [$organization, $admin, $profile];
    }

    /** @return array{0: string, 1: string} */
    private function geographyIds(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');

        self::assertNotNull($country);
        $regions = $geography->regionsOf((string) $country->id);
        self::assertNotEmpty($regions);

        return [(string) $country->id, (string) $regions[0]->id];
    }
}

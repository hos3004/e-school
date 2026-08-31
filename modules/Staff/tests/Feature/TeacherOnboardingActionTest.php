<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\CreateTeacherOnboardingAction;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffAccountMode;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Events\StaffProfileCreated;
use Modules\Staff\Domain\Events\TeacherContractCreated;
use Modules\Staff\Domain\Events\TeacherRateCreated;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Domain\Models\TeacherCourse;
use Modules\Staff\Domain\Models\TeacherRate;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class TeacherOnboardingActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GeographySeeder::class);
        $this->seed(AccessControlSeeder::class);
        Event::fake([
            UserRegistered::class,
            StaffProfileCreated::class,
            TeacherContractCreated::class,
            TeacherRateCreated::class,
        ]);
    }

    public function test_new_teacher_account_profile_contract_rate_qualification_and_role_are_created_atomically(): void
    {
        [$organization, $actor, $course, $countryId, $regionId] = $this->context();
        $reason = 'اعتماد المعلم بعد مراجعة الهوية والمؤهلات والعقد';

        $profile = app(CreateTeacherOnboardingAction::class)->execute(
            $this->data(
                mode: StaffAccountMode::NewAccount,
                courseId: (string) $course->id,
                countryId: $countryId,
                regionId: $regionId,
                reason: $reason,
                basis: ContractBasis::Hybrid,
            ),
            (string) $organization->id,
            (string) $actor->id,
        );

        $user = User::query()->findOrFail($profile->user_id);
        $contract = TeacherContract::query()->where('staff_profile_id', $profile->id)->firstOrFail();
        $rate = TeacherRate::query()->where('teacher_contract_id', $contract->id)->firstOrFail();
        $teacherRoleId = DB::table('roles')->whereNull('organization_id')->where('name', 'teacher')->value('id');
        $modelType = app(UserQueryService::class)->modelType();

        self::assertSame('New Teacher', $user->name);
        self::assertSame('new.teacher', $user->username);
        self::assertSame(ContractBasis::Hybrid, $contract->basis);
        self::assertSame(500000, $contract->base_amount);
        self::assertSame(15000, $rate->amount);
        self::assertTrue(TeacherCourse::query()->where([
            'staff_profile_id' => (string) $profile->id,
            'course_id' => (string) $course->id,
            'qualified_by' => (string) $actor->id,
        ])->exists());
        self::assertTrue(DB::table('model_has_roles')->where([
            'role_id' => $teacherRoleId,
            'model_type' => $modelType,
            'model_id' => (string) $user->id,
        ])->exists());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'staff.teacher_onboarded',
            'auditable_id' => (string) $profile->id,
            'reason' => $reason,
        ])->exists());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'permissions.role_assigned',
            'auditable_id' => (string) $user->id,
            'reason' => $reason,
        ])->exists());
    }

    public function test_existing_account_can_be_linked_without_creating_another_user(): void
    {
        [$organization, $actor, $course, $countryId, $regionId] = $this->context();
        $existing = User::factory()->inOrganization((string) $organization->id)->create([
            'name' => 'Existing Teacher',
            'email' => 'existing.teacher@example.test',
        ]);
        $usersBefore = User::query()->count();

        $profile = app(CreateTeacherOnboardingAction::class)->execute(
            $this->data(
                mode: StaffAccountMode::ExistingAccount,
                courseId: (string) $course->id,
                countryId: $countryId,
                regionId: $regionId,
                reason: 'ربط حساب المعلم الموجود بعد التحقق الإداري',
                basis: ContractBasis::Salary,
                existingUserId: (string) $existing->id,
            ),
            (string) $organization->id,
            (string) $actor->id,
        );

        self::assertSame((string) $existing->id, $profile->user_id);
        self::assertSame($usersBefore, User::query()->count());
        self::assertSame(0, TeacherRate::query()->count());
        self::assertSame(500000, TeacherContract::query()->where('staff_profile_id', $profile->id)->value('base_amount'));
    }

    public function test_missing_teacher_role_rolls_back_every_created_record_and_audit_entry(): void
    {
        [$organization, $actor, $course, $countryId, $regionId] = $this->context();
        $reason = 'اختبار التراجع الكامل عند تعذر إسناد دور المعلم';
        DB::table('roles')->whereNull('organization_id')->where('name', 'teacher')->delete();

        try {
            app(CreateTeacherOnboardingAction::class)->execute(
                $this->data(
                    mode: StaffAccountMode::NewAccount,
                    courseId: (string) $course->id,
                    countryId: $countryId,
                    regionId: $regionId,
                    reason: $reason,
                    basis: ContractBasis::PerSession,
                ),
                (string) $organization->id,
                (string) $actor->id,
            );

            self::fail('The onboarding action must fail when the configured teacher role is missing.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('accesscontrol.role.not_found', $violation->rule);
        }

        self::assertFalse(User::query()->where('username', 'new.teacher')->exists());
        self::assertSame(0, StaffProfile::query()->count());
        self::assertSame(0, TeacherContract::query()->count());
        self::assertSame(0, TeacherRate::query()->count());
        self::assertSame(0, TeacherCourse::query()->count());
        self::assertFalse(DB::table('audit_log')->where('reason', $reason)->exists());
    }

    public function test_account_from_another_organization_cannot_be_linked(): void
    {
        [$organization, $actor, $course, $countryId, $regionId] = $this->context();
        $foreignOrganization = Organization::factory()->create();
        $foreignAccount = User::factory()->inOrganization((string) $foreignOrganization->id)->create();

        try {
            app(CreateTeacherOnboardingAction::class)->execute(
                $this->data(
                    mode: StaffAccountMode::ExistingAccount,
                    courseId: (string) $course->id,
                    countryId: $countryId,
                    regionId: $regionId,
                    reason: 'محاولة ربط حساب من مؤسسة أخرى',
                    basis: ContractBasis::Salary,
                    existingUserId: (string) $foreignAccount->id,
                ),
                (string) $organization->id,
                (string) $actor->id,
            );

            self::fail('Cross-organization accounts must never be linkable.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('staff.existing_account_not_found', $violation->rule);
        }

        self::assertFalse(StaffProfile::query()->where('user_id', $foreignAccount->id)->exists());
    }

    public function test_create_teacher_page_is_registered_and_renders_for_an_authorized_operator(): void
    {
        [$organization, $actor] = $this->context();
        Gate::before(static fn (): bool => true);
        Filament::setCurrentPanel('admin');

        $this->actingAs($actor)
            ->get(StaffProfileResource::getUrl('create', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('staff::admin.onboarding.new_account'))
            ->assertSeeText(__('staff::admin.onboarding.steps.contract'));

        self::assertSame((string) $organization->id, (string) $actor->organization_id);
    }

    /** @return array{Organization, User, Course, string, string} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        [$countryId, $regionId] = $this->geographyIds();
        $program = Program::factory()->create([
            'organization_id' => (string) $organization->id,
            'is_active' => true,
        ]);
        $level = Level::factory()->create(['program_id' => (string) $program->id]);
        $course = Course::factory()->create([
            'organization_id' => (string) $organization->id,
            'level_id' => (string) $level->id,
            'is_active' => true,
            'session_mode' => SessionMode::Group,
        ]);

        return [$organization, $actor, $course, $countryId, $regionId];
    }

    /** @return array<string, mixed> */
    private function data(
        StaffAccountMode $mode,
        string $courseId,
        string $countryId,
        string $regionId,
        string $reason,
        ContractBasis $basis,
        ?string $existingUserId = null,
    ): array {
        return [
            'account_mode' => $mode->value,
            'existing_user_id' => $existingUserId,
            'full_name' => $mode === StaffAccountMode::NewAccount ? 'New Teacher' : null,
            'email' => $mode === StaffAccountMode::NewAccount ? 'new.teacher@example.test' : null,
            'phone' => null,
            'username' => $mode === StaffAccountMode::NewAccount ? 'new.teacher' : null,
            'password' => $mode === StaffAccountMode::NewAccount ? 'Z7!eSchool-Teacher-2026#Qp9' : null,
            'password_confirmation' => $mode === StaffAccountMode::NewAccount ? 'Z7!eSchool-Teacher-2026#Qp9' : null,
            'locale' => 'ar',
            'timezone' => 'UTC',
            'staff_code' => 'TCH-ONBOARD-001',
            'employment_type' => EmploymentType::Contractor->value,
            'gender' => StaffGender::Female->value,
            'country_id' => $countryId,
            'region_id' => $regionId,
            'date_of_birth' => '1990-05-15',
            'hired_at' => '2026-08-24',
            'bio' => 'معلمة معتمدة لتدريس البرنامج.',
            'specializations' => ['Quran', 'Arabic'],
            'contract_basis' => $basis->value,
            'contract_effective_from' => '2026-08-24',
            'contract_effective_to' => null,
            'currency' => 'EGP',
            'base_amount_major' => $basis->requiresBaseAmount() ? '5000.00' : null,
            'default_rate_major' => $basis->requiresRates() ? '150.00' : null,
            'monthly_target_sessions' => 40,
            'target_admin_tasks' => 2,
            'target_training_sessions' => 1,
            'course_ids' => [$courseId],
            'qualification_notes' => 'تمت مراجعة المؤهل.',
            'onboarding_reason' => $reason,
        ];
    }

    /** @return array{string, string} */
    private function geographyIds(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        $regions = $geography->regionsOf((string) $country?->id);

        self::assertNotNull($country);
        self::assertNotEmpty($regions);

        return [(string) $country->id, $regions[0]->id];
    }
}

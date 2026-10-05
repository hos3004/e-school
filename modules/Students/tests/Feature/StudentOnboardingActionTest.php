<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
use Modules\Students\Application\Actions\CreateStudentOnboardingAction;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentAccountMode;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Events\RegistrationSubmitted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class StudentOnboardingActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GeographySeeder::class);
        $this->seed(AccessControlSeeder::class);
        Event::fake([
            UserRegistered::class,
            RegistrationSubmitted::class,
            RegistrationAccepted::class,
        ]);
    }

    public function test_new_person_is_created_accepted_and_given_the_student_role_atomically(): void
    {
        [$organization, $reviewer, $program, $course, $countryId, $regionId] = $this->context();
        $reason = 'استيفاء بيانات القبول والتحاق الطالب بالبرنامج المحدد';

        $profile = app(CreateStudentOnboardingAction::class)->execute(
            $this->data(
                mode: StudentAccountMode::NewAccount,
                programId: (string) $program->id,
                courseId: (string) $course->id,
                countryId: $countryId,
                regionId: $regionId,
                reason: $reason,
            ),
            (string) $organization->id,
            (string) $reviewer->id,
        );

        $user = User::query()->findOrFail($profile->user_id);
        $application = RegistrationApplication::query()->where('student_profile_id', $profile->id)->firstOrFail();
        $studentRoleId = DB::table('roles')->whereNull('organization_id')->where('name', 'student')->value('id');
        $modelType = app(UserQueryService::class)->modelType();

        self::assertSame('New Student', $user->name);
        self::assertSame('new.student', $user->username);
        self::assertSame(RegistrationStatus::WaitingAssignment, $application->status);
        self::assertSame($reason, $application->decision_reason);
        self::assertSame('Cairo', $profile->city);
        self::assertTrue(DB::table('model_has_roles')->where([
            'role_id' => $studentRoleId,
            'model_type' => $modelType,
            'model_id' => (string) $user->id,
        ])->exists());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'academic_status.registration_accepted',
            'auditable_id' => (string) $application->id,
            'reason' => $reason,
        ])->exists());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'permissions.role_assigned',
            'auditable_id' => (string) $user->id,
            'reason' => $reason,
        ])->exists());
    }

    public function test_existing_account_can_be_linked_without_creating_a_second_person(): void
    {
        [$organization, $reviewer, $program, $course, $countryId, $regionId] = $this->context();
        $existing = User::factory()->inOrganization((string) $organization->id)->create([
            'name' => 'Existing Student',
            'email' => 'existing.student@example.test',
        ]);
        $usersBefore = User::query()->count();

        $profile = app(CreateStudentOnboardingAction::class)->execute(
            $this->data(
                mode: StudentAccountMode::ExistingAccount,
                programId: (string) $program->id,
                courseId: (string) $course->id,
                countryId: $countryId,
                regionId: $regionId,
                reason: 'ربط الحساب الموجود بعد التحقق الإداري',
                existingUserId: (string) $existing->id,
            ),
            (string) $organization->id,
            (string) $reviewer->id,
        );

        self::assertSame((string) $existing->id, $profile->user_id);
        self::assertSame($usersBefore, User::query()->count());
        self::assertSame(
            RegistrationStatus::WaitingAssignment,
            $profile->registrationApplication?->status,
        );
    }

    public function test_failure_after_account_creation_rolls_back_the_whole_onboarding_flow(): void
    {
        [$organization, $reviewer, $program, $course, $countryId, $regionId] = $this->context();
        DB::table('roles')->whereNull('organization_id')->where('name', 'student')->delete();

        try {
            app(CreateStudentOnboardingAction::class)->execute(
                $this->data(
                    mode: StudentAccountMode::NewAccount,
                    programId: (string) $program->id,
                    courseId: (string) $course->id,
                    countryId: $countryId,
                    regionId: $regionId,
                    reason: 'اختبار التراجع الكامل عند تعذر إسناد الدور',
                ),
                (string) $organization->id,
                (string) $reviewer->id,
            );

            self::fail('The onboarding action should fail when the configured student role is missing.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('accesscontrol.role.not_found', $violation->rule);
        }

        self::assertFalse(User::query()->where('username', 'new.student')->exists());
        self::assertSame(0, RegistrationApplication::query()->count());
        self::assertSame(0, StudentProfile::query()->count());
        self::assertFalse(DB::table('audit_log')->where('reason', 'اختبار التراجع الكامل عند تعذر إسناد الدور')->exists());
    }

    public function test_an_existing_account_from_another_organization_cannot_be_linked(): void
    {
        [$organization, $reviewer, $program, $course, $countryId, $regionId] = $this->context();
        $foreignOrganization = Organization::factory()->create();
        $foreignAccount = User::factory()->inOrganization((string) $foreignOrganization->id)->create();

        try {
            app(CreateStudentOnboardingAction::class)->execute(
                $this->data(
                    mode: StudentAccountMode::ExistingAccount,
                    programId: (string) $program->id,
                    courseId: (string) $course->id,
                    countryId: $countryId,
                    regionId: $regionId,
                    reason: 'محاولة ربط حساب من مؤسسة أخرى',
                    existingUserId: (string) $foreignAccount->id,
                ),
                (string) $organization->id,
                (string) $reviewer->id,
            );

            self::fail('Cross-organization accounts must never be linkable.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('students.existing_account_not_found', $violation->rule);
        }

        self::assertFalse(StudentProfile::query()->where('user_id', $foreignAccount->id)->exists());
        self::assertFalse(RegistrationApplication::query()->where('user_id', $foreignAccount->id)->exists());
    }

    /**
     * @return array{Organization, User, Program, Course, string, string}
     */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $reviewer = User::factory()->inOrganization((string) $organization->id)->create();
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

        return [$organization, $reviewer, $program, $course, $countryId, $regionId];
    }

    /** @return array<string, mixed> */
    private function data(
        StudentAccountMode $mode,
        string $programId,
        string $courseId,
        string $countryId,
        string $regionId,
        string $reason,
        ?string $existingUserId = null,
    ): array {
        return [
            'account_mode' => $mode->value,
            'existing_user_id' => $existingUserId,
            'full_name' => $mode === StudentAccountMode::NewAccount ? 'New Student' : null,
            'email' => $mode === StudentAccountMode::NewAccount ? 'new.student@example.test' : null,
            'username' => $mode === StudentAccountMode::NewAccount ? 'new.student' : null,
            'password' => $mode === StudentAccountMode::NewAccount ? 'Z7!eSchool-Onboarding-2026#Qp9' : null,
            'password_confirmation' => $mode === StudentAccountMode::NewAccount ? 'Z7!eSchool-Onboarding-2026#Qp9' : null,
            'locale' => 'ar',
            'timezone' => 'UTC',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country_id' => $countryId,
            'region_id' => $regionId,
            'nationality' => 'EG',
            'city' => 'Cairo',
            'preferred_language' => 'ar',
            'preferred_program_id' => $programId,
            'preferred_course_id' => $courseId,
            'acceptance_reason' => $reason,
            'notes' => 'Onboarded from the administration panel.',
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

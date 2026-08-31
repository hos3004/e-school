<?php

declare(strict_types=1);

namespace Tests\Feature\PhaseOne;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramEligibility;
use Modules\AccessControl\Application\Actions\GrantModelPermissionAction;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Events\StudentAssignedToGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Application\Actions\ApproveTeacherAvailabilityAction;
use Modules\Staff\Application\Actions\SetTeacherAvailability;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Events\TeacherAvailabilityApproved;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherCourse;
use Modules\Students\Application\Actions\RejectRegistrationApplicationAction;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Events\RegistrationRejected;
use Modules\Students\Domain\Events\RegistrationSubmitted;
use Modules\Students\Domain\Events\StudentAssignedToTeacher;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class Task02AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
    }

    public function test_public_registration_acceptance_and_placement_are_tenant_safe_and_atomic(): void
    {
        (new GeographySeeder)->run();

        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        [$countryId, $regionId] = $this->geographyIds();

        $reviewer = User::factory()->inOrganization((string) $organization->id)->create();
        $otherReviewer = User::factory()->inOrganization((string) $otherOrganization->id)->create();
        $teacherUser = User::factory()->inOrganization((string) $organization->id)->create();

        (new AccessControlSeeder)->run();
        app(PermissionGateRegistrar::class)->register();
        $this->grantPlacementPermissions($reviewer);
        $this->grantPlacementPermissions($otherReviewer);

        $program = Program::factory()->create([
            'organization_id' => (string) $organization->id,
            'program_type' => ProgramType::Ongoing,
            'end_date' => null,
            'target_gender' => TargetGender::All,
        ]);
        $level = Level::factory()->create(['program_id' => (string) $program->id]);
        $course = Course::factory()->create([
            'organization_id' => (string) $organization->id,
            'level_id' => (string) $level->id,
            'session_mode' => SessionMode::Group,
        ]);
        $eligibility = ProgramEligibility::query()->create([
            'program_id' => (string) $program->id,
            'countries' => [$countryId],
            'regions' => [$regionId],
            'age_from' => 13,
            'age_to' => 30,
            'gender' => TargetGender::Female,
            'manual_approval_required' => true,
            'teacher_gender_rule' => 'any',
            'requires_individual_sessions' => false,
        ]);

        $teacherProfile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $teacherUser->id,
            'staff_code' => 'T-'.strtoupper(substr((string) $teacherUser->id, -8)),
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Male,
            'country_id' => $countryId,
            'region_id' => $regionId,
            'hired_at' => CarbonImmutable::today('UTC'),
        ]);
        $group = Group::query()->create([
            'organization_id' => (string) $organization->id,
            'code' => 'G-'.strtoupper(substr((string) $program->id, -8)),
            'name' => ['ar' => 'مجموعة اختبار القبول', 'en' => 'Acceptance group'],
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => GroupStatus::Active,
            'starts_on' => CarbonImmutable::today('UTC'),
        ]);
        GroupProgram::query()->create([
            'group_id' => (string) $group->id,
            'program_id' => (string) $program->id,
        ]);
        GroupTeacher::query()->create([
            'group_id' => (string) $group->id,
            'staff_profile_id' => (string) $teacherProfile->id,
            'course_id' => (string) $course->id,
            'role' => GroupTeacherRole::Lead,
            'assigned_from' => CarbonImmutable::today('UTC'),
        ]);

        Event::fake([
            RegistrationSubmitted::class,
            RegistrationAccepted::class,
            TeacherAvailabilityApproved::class,
            StudentAssignedToTeacher::class,
            StudentAssignedToGroup::class,
        ]);

        $availability = app(SetTeacherAvailability::class)->execute(
            profile: $teacherProfile,
            weekday: 1,
            startTime: '08:00',
            endTime: '10:00',
            timezone: 'UTC',
            effectiveFrom: CarbonImmutable::today('UTC'),
        );
        $approvedAvailability = app(ApproveTeacherAvailabilityAction::class)->execute(
            $availability,
            (string) $reviewer->id,
            'اعتماد الفترة ضمن سيناريو القبول التشغيلي للمرحلة الأولى',
        );
        app(ApproveTeacherAvailabilityAction::class)->execute(
            $approvedAvailability,
            (string) $reviewer->id,
            'إعادة إرسال نفس قرار الاعتماد للتحقق من عدم تكرار الحدث',
        );

        self::assertSame(TeacherAvailabilityApprovalStatus::Approved, $approvedAvailability->approval_status);
        self::assertSame((string) $reviewer->id, $approvedAvailability->approved_by);
        self::assertNotNull($approvedAvailability->approved_at);
        Event::assertDispatchedTimes(TeacherAvailabilityApproved::class, 1);

        $payload = [
            'full_name' => 'طالب المرحلة الأولى',
            'date_of_birth' => '2005-05-15',
            'gender' => StudentGender::Male->value,
            'country_id' => $countryId,
            'region_id' => $regionId,
            'email' => 'phase1.student@example.test',
            'preferred_program_id' => (string) $program->id,
            'preferred_course_id' => (string) $course->id,
        ];

        $this->postJson(
            '/api/public/organizations/'.$organization->id.'/registration-applications',
            ['organization_id' => (string) $otherOrganization->id, ...$payload, 'email' => 'blocked@example.test'],
        )->assertUnprocessable()->assertJsonValidationErrors('organization_id');

        $registrationResponse = $this->postJson(
            '/api/public/organizations/'.$organization->id.'/registration-applications',
            $payload,
        )->assertCreated()
            ->assertJsonPath('status', RegistrationStatus::Submitted->value);

        $applicationId = (string) $registrationResponse->json('application_id');
        $application = RegistrationApplication::query()->findOrFail($applicationId);
        self::assertSame((string) $organization->id, $application->organization_id);
        self::assertSame((string) $program->id, $application->preferred_program_id);
        self::assertSame((string) $course->id, $application->preferred_course_id);
        self::assertNotNull($application->user_id);
        self::assertFalse(StudentProfile::query()->where('user_id', $application->user_id)->exists());
        self::assertFalse(User::query()->where('email', 'blocked@example.test')->exists());
        Event::assertDispatched(RegistrationSubmitted::class);

        $acceptUrl = '/api/registration-applications/'.$applicationId.'/accept';
        $acceptancePayload = ['reason' => 'استيفاء شروط القبول الأكاديمي'];
        $this->actingAs($otherReviewer, 'sanctum')->postJson($acceptUrl, $acceptancePayload)->assertForbidden();
        $this->actingAs($reviewer, 'sanctum')->postJson($acceptUrl, $acceptancePayload)
            ->assertOk()
            ->assertJsonPath('data.status', RegistrationStatus::WaitingAssignment->value);

        $application->refresh();
        self::assertNotNull($application->student_profile_id);
        Event::assertDispatchedTimes(RegistrationAccepted::class, 1);

        $placementUrl = '/api/groups/'.$group->id.'/students';
        $placementPayload = [
            'student_profile_id' => (string) $application->student_profile_id,
            'program_id' => (string) $program->id,
            'course_id' => (string) $course->id,
            'reason' => 'تسكين الطالب حسب البرنامج والكورس المقبولين',
        ];

        $payloadWithoutReason = $placementPayload;
        unset($payloadWithoutReason['reason']);
        $this->actingAs($reviewer, 'sanctum')->postJson($placementUrl, $payloadWithoutReason)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->assertPlacementWasRolledBack($application);

        $this->actingAs($reviewer, 'sanctum')->postJson($placementUrl, $placementPayload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'enrollments.eligibility_blocked');
        $this->assertPlacementWasRolledBack($application);

        $eligibility->update(['gender' => TargetGender::Male]);
        $this->actingAs($reviewer, 'sanctum')->postJson($placementUrl, $placementPayload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'groups.teacher_not_qualified');
        $this->assertPlacementWasRolledBack($application);

        TeacherCourse::query()->create([
            'staff_profile_id' => (string) $teacherProfile->id,
            'course_id' => (string) $course->id,
            'qualified_at' => now()->utc(),
            'qualified_by' => (string) $reviewer->id,
        ]);

        $this->actingAs($otherReviewer, 'sanctum')->postJson($placementUrl, $placementPayload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'enrollments.organization_mismatch');
        $this->assertPlacementWasRolledBack($application);

        $this->actingAs($reviewer, 'sanctum')->postJson($placementUrl, $placementPayload)
            ->assertCreated()
            ->assertJsonPath('data.organization_id', (string) $organization->id)
            ->assertJsonPath('data.status', 'active');
        $this->actingAs($reviewer, 'sanctum')->postJson($placementUrl, $placementPayload)->assertOk();

        $application->refresh();
        self::assertSame(RegistrationStatus::Assigned, $application->status);
        self::assertSame(1, DB::table('group_memberships')->where('student_profile_id', $application->student_profile_id)->count());
        self::assertSame(1, DB::table('enrollments')->where('student_profile_id', $application->student_profile_id)->count());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'academic_status.registration_accepted',
            'auditable_id' => $applicationId,
            'reason' => $acceptancePayload['reason'],
        ])->exists());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'enrollment.placed',
            'auditable_id' => (string) $application->student_profile_id,
            'reason' => $placementPayload['reason'],
        ])->exists());
        Event::assertDispatchedTimes(StudentAssignedToTeacher::class, 1);
        Event::assertDispatchedTimes(StudentAssignedToGroup::class, 1);
    }

    public function test_rejection_persists_the_required_reason_in_the_real_column(): void
    {
        (new GeographySeeder)->run();

        $organization = Organization::factory()->create();
        [$countryId, $regionId] = $this->geographyIds();
        $reviewer = User::factory()->inOrganization((string) $organization->id)->create();
        $applicant = User::factory()->inOrganization((string) $organization->id)->create();
        $application = RegistrationApplication::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $applicant->id,
            'status' => RegistrationStatus::Submitted,
            'full_name' => 'طالب غير مكتمل',
            'date_of_birth' => '2005-05-15',
            'gender' => StudentGender::Male,
            'country_id' => $countryId,
            'region_id' => $regionId,
            'email' => $applicant->email,
            'submitted_at' => now()->utc(),
        ]);

        Event::fake([RegistrationRejected::class]);
        $reason = 'عدم استيفاء البيانات الشخصية';
        $rejected = app(RejectRegistrationApplicationAction::class)->execute(
            $application,
            $reason,
            (string) $reviewer->id,
        );

        self::assertSame(RegistrationStatus::Rejected, $rejected->status);
        self::assertSame($reason, $rejected->decision_reason);
        self::assertSame((string) $reviewer->id, $rejected->reviewed_by);
        Event::assertDispatchedTimes(RegistrationRejected::class, 1);
    }

    /** @return array{0: string, 1: string} */
    private function geographyIds(): array
    {
        $countryId = DB::table('countries')->where('is_active', true)->orderBy('sort_order')->value('id');
        $regionId = DB::table('regions')->where('country_id', $countryId)->where('is_active', true)->orderBy('sort_order')->value('id');

        self::assertIsString($countryId);
        self::assertIsString($regionId);

        return [$countryId, $regionId];
    }

    private function grantPlacementPermissions(User $user): void
    {
        $grant = app(GrantModelPermissionAction::class);

        foreach (['student.create', 'enrollment.create', 'group.manage'] as $permission) {
            $grant->execute(
                permissionName: $permission,
                modelType: $user->getMorphClass(),
                modelId: (string) $user->id,
            );
        }
    }

    private function assertPlacementWasRolledBack(RegistrationApplication $application): void
    {
        $application->refresh();
        self::assertSame(RegistrationStatus::WaitingAssignment, $application->status);
        self::assertSame(0, DB::table('group_memberships')->where('student_profile_id', $application->student_profile_id)->count());
        self::assertSame(0, DB::table('enrollments')->where('student_profile_id', $application->student_profile_id)->count());
    }
}

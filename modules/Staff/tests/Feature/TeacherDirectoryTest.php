<?php

declare(strict_types=1);

namespace Modules\Staff\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Contracts\TeacherDirectoryQueries;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Domain\Models\TeacherCourse;
use Modules\Staff\Presentation\Filament\Pages\TeachersDirectory;
use Tests\TestCase;

final class TeacherDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_returns_only_active_account_teachers_with_real_batched_metrics(): void
    {
        $fixture = $this->fixture();

        $directory = app(TeacherDirectoryQueries::class)->directoryFor(
            (string) $fixture['organization']->id,
            [$fixture['teacher']->id, $fixture['suspendedProfile']->id],
        );

        // المعلم ذو الحساب الموقوف يُستبعد — الدليل للحسابات النشطة فقط.
        self::assertArrayHasKey((string) $fixture['teacher']->id, $directory);
        self::assertArrayNotHasKey((string) $fixture['suspendedProfile']->id, $directory);

        $data = $directory[(string) $fixture['teacher']->id];

        self::assertSame('المعلم الحقيقي', $data->name);
        self::assertSame('active', $data->accountStatus);
        self::assertSame('full_time', $data->employmentType);
        self::assertNull($data->terminatedAt);

        // مؤشرات حقيقية من بيانات المجموعات والحصص الفعلية:
        self::assertSame(1, $data->qualifiedCoursesCount);
        self::assertSame(1, $data->activeGroups);
        self::assertSame(2, $data->upcomingSessions); // مجدولة + مؤكدة في المستقبل
        self::assertSame(1, $data->completedThisMonth);
        self::assertSame(1, $data->cancelledThisMonth);
    }

    public function test_availability_flag_reflects_approved_and_current_availability(): void
    {
        $fixture = $this->fixture();
        /** @var StaffProfile $available */
        $available = $fixture['teacher'];
        /** @var StaffProfile $unavailable */
        $unavailable = $fixture['secondProfile'];

        TeacherAvailability::query()->create([
            'staff_profile_id' => $available->id,
            'weekday' => (int) now('UTC')->dayOfWeek,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'timezone' => 'UTC',
            'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
            'effective_from' => CarbonImmutable::today(),
        ]);

        $ids = [(string) $available->id, (string) $unavailable->id];
        $withAvailability = app(TeacherDirectoryQueries::class)->withActiveAvailability($ids);

        self::assertContains((string) $available->id, $withAvailability);
        self::assertNotContains((string) $unavailable->id, $withAvailability);

        $directory = app(TeacherDirectoryQueries::class)->directoryFor(
            (string) $fixture['organization']->id,
            $ids,
        );

        self::assertTrue($directory[(string) $available->id]->hasApprovedAvailability);
        self::assertSame(false, $directory[(string) $unavailable->id]?->hasApprovedAvailability ?? false);
    }

    public function test_directory_page_is_gated_by_staff_view_any_permission(): void
    {
        $user = User::factory()->create();
        $this->be($user);

        Gate::define('staff.view.any', static fn (): bool => false);

        self::assertFalse(TeachersDirectory::canAccess());

        Gate::define('staff.view.any', static fn (): bool => true);

        self::assertTrue(TeachersDirectory::canAccess());
    }

    /**
     * مؤسسة كاملة: معلّمان (أحدهما حسابه موقوف)، مجموعة نشطة بإسناد معلّم،
     * تأهيل كورس، وحصص بأربع حالات مختلفة.
     *
     * @return array<string, object>
     */
    private function fixture(): array
    {
        $organization = Organization::factory()->create();
        $teacherUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'المعلم الحقيقي']);
        $suspendedUser = User::factory()
            ->inOrganization((string) $organization->id)
            ->create(['name' => 'حساب موقوف', 'status' => UserStatus::Suspended]);
        $secondUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'المعلم الثاني']);

        $program = Program::factory()->create([
            'organization_id' => $organization->id,
            'name' => ['ar' => 'برنامج الاختبار', 'en' => 'Test Program'],
        ]);
        $level = Level::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create([
            'organization_id' => $organization->id,
            'level_id' => $level->id,
            'name' => ['ar' => 'مقرر الاختبار', 'en' => 'Test Course'],
            'session_mode' => SessionMode::Group,
        ]);

        /** @var StaffProfile $teacher */
        $teacher = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $teacherUser->id,
            'staff_code' => 'T-DIR-'.str()->random(3),
            'employment_type' => EmploymentType::FullTime,
            'gender' => StaffGender::Male,
            'hired_at' => '2025-01-01',
        ]);

        /** @var StaffProfile $suspendedProfile */
        $suspendedProfile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $suspendedUser->id,
            'staff_code' => 'T-SUS-'.str()->random(3),
            'employment_type' => EmploymentType::PartTime,
            'gender' => StaffGender::Female,
            'hired_at' => '2025-01-01',
        ]);

        /** @var StaffProfile $secondProfile */
        $secondProfile = StaffProfile::query()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $secondUser->id,
            'staff_code' => 'T-SEC-'.str()->random(3),
            'employment_type' => EmploymentType::PartTime,
            'gender' => StaffGender::Female,
            'hired_at' => '2025-01-01',
        ]);

        TeacherCourse::query()->create([
            'staff_profile_id' => $teacher->id,
            'course_id' => $course->id,
            'qualified_at' => now('UTC')->subMonths(2),
            'qualified_by' => (string) $teacherUser->id,
        ]);

        /** @var Group $group */
        $group = Group::query()->create([
            'organization_id' => (string) $organization->id,
            'code' => 'GR-DIR',
            'name' => ['ar' => 'مجموعة الدليل', 'en' => 'Directory Group'],
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => GroupStatus::Active,
            'starts_on' => '2026-01-01',
        ]);

        GroupTeacher::query()->create([
            'group_id' => $group->id,
            'staff_profile_id' => $teacher->id,
            'role' => GroupTeacherRole::Lead,
            'assigned_from' => now('UTC')->subMonth(),
        ]);

        $now = CarbonImmutable::now('UTC');

        foreach ([
            [SessionStatus::Scheduled, $now->addDays(3), null],
            [SessionStatus::Confirmed, $now->addDays(7), null],
            [SessionStatus::Completed, $now->subDays(2), null],
            [SessionStatus::CancelledBySchool, $now->subDays(4), null],
            [SessionStatus::Completed, $now->subMonths(2), null], // خارج الشهر الحالي
        ] as [$status, $start, $_]) {
            Session::query()->create([
                'organization_id' => (string) $organization->id,
                'group_id' => $group->id,
                'course_id' => $course->id,
                'staff_profile_id' => $teacher->id,
                'session_type' => 'group',
                'status' => $status,
                'scheduled_start' => $start,
                'scheduled_end' => $start->addHour(),
                'title' => ['ar' => 'حصة اختبار', 'en' => 'Test session'],
            ]);
        }

        return compact('organization', 'teacherUser', 'teacher', 'suspendedUser', 'suspendedProfile', 'secondUser', 'secondProfile');
    }
}

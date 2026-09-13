<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Program;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * برامج الطالب ومعلموه من صفحة ملفه — في مدرسة تعمل بالكورسات الفردية بلا مجموعات.
 *
 * المحروس: القيد في برنامج آخر لا يحتاج مجموعة، وإسناد المعلم يولّد حصصًا،
 * وإزالته تلغي القادم فقط ويبقى القيد، وتغييره ينقل الحصص القادمة بلا استبدال.
 */
final class ConsoleStudentProgramsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'student.view.any', 'schedule.manage', 'schedule.view',
        'enrollment.create', 'enrollment.view', 'enrollment.freeze',
    ];

    private string $organizationId;

    private string $courseId;

    private string $programId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }
        $this->organizationId = Fixtures::organizationId();
        $this->courseId = Fixtures::courseId();
        DB::table('courses')->where('id', $this->courseId)
            ->update(['session_mode' => SessionMode::Individual->value]);
        $this->programId = (string) DB::table('courses')
            ->join('levels', 'courses.level_id', '=', 'levels.id')
            ->where('courses.id', $this->courseId)
            ->value('levels.program_id');
    }

    public function test_profile_lists_programs_and_assignable_courses_without_any_group(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $this->teacher();

        $this->assertDatabaseCount('groups', 0);

        $this->actingAs($admin, 'web')->get('/manage/students/'.$student->id)->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('programs.enrollments', 1)
                ->has('programs.assignableCourses', 1)
                ->where('programs.assignableCourses.0.value', $this->courseId)
                ->has('programs.schedules', 0)
                ->where('placement', null));
    }

    public function test_a_teacher_can_be_assigned_then_changed_then_removed(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $first = $this->teacher();
        $second = $this->teacher();
        $url = '/manage/students/'.$student->id.'/teacher';
        $slot = ['weekday' => 1, 'start_time' => '10:00'];
        $payload = [
            'course_id' => $this->courseId,
            'staff_profile_id' => $first,
            'weekly_slots' => [$slot],
            'duration_minutes' => (int) (config('scheduling.individual_session_durations')[0] ?? 25),
            'interval_weeks' => 1,
            'timezone' => 'UTC',
            'starts_on' => CarbonImmutable::now('UTC')->toDateString(),
        ];

        $this->actingAs($admin, 'web')->post($url, $payload)->assertSessionHasErrors('reason');
        $this->post($url, [...$payload, 'reason' => 'إسناد معلم للطالب'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $schedule = Schedule::query()->where('student_profile_id', (string) $student->id)->firstOrFail();
        $this->assertSame($first, (string) $schedule->staff_profile_id);
        $this->assertTrue((bool) $schedule->is_active);

        $this->put($url, [
            'schedule_id' => (string) $schedule->getKey(),
            'staff_profile_id' => $second,
            'reason' => 'تغيير المعلم بطلب ولي الأمر',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($second, (string) $schedule->refresh()->staff_profile_id);
        $this->assertDatabaseCount('session_substitutions', 0);

        $future = $this->futureSession($schedule, $second);
        $this->delete($url, [
            'schedule_id' => (string) $schedule->getKey(),
            'reason' => 'إيقاف الدراسة مع هذا المعلم',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertFalse((bool) $schedule->refresh()->is_active);
        $this->assertNotSame(SessionStatus::Scheduled, $future->refresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => (string) $student->id,
            'program_id' => $this->programId,
            'status' => EnrollmentStatus::Active->value,
        ]);
    }

    public function test_a_student_can_be_enrolled_in_another_program_without_a_group(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $other = Program::factory()->create([
            'organization_id' => $this->organizationId, 'is_active' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->post('/manage/students/'.$student->id.'/programs', ['program_id' => (string) $other->id])
            ->assertSessionHasErrors('reason');

        $this->post('/manage/students/'.$student->id.'/programs', [
            'program_id' => (string) $other->id, 'reason' => 'انضمام الطالب لبرنامج إضافي',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => (string) $student->id,
            'program_id' => (string) $other->id,
            'status' => EnrollmentStatus::Active->value,
        ]);
        $this->assertSame(2, DB::table('enrollments')
            ->where('student_profile_id', (string) $student->id)->count());
    }

    public function test_freezing_one_program_is_offered_per_enrollment(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $enrollment = Enrollment::query()->where('student_profile_id', (string) $student->id)->firstOrFail();

        $this->actingAs($admin, 'web')->get('/manage/students/'.$student->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'programs.enrollments.0.freezeUrl',
                url('/manage/enrollments/'.$enrollment->id.'/freeze'),
            ));
    }

    public function test_everything_is_closed_without_permissions(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $teacher = $this->teacher();

        $this->permissions = ['admin.panel.access', 'student.view.any'];
        $this->actingAs($admin, 'web')->get('/manage/students/'.$student->id)->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('programs.enrollUrl', null)
                ->where('programs.assignUrl', null)
                ->has('programs.schedules', 0));
        $this->post('/manage/students/'.$student->id.'/programs', [
            'program_id' => $this->programId, 'reason' => 'بلا صلاحية',
        ])->assertForbidden();
        $this->post('/manage/students/'.$student->id.'/teacher', [
            'course_id' => $this->courseId, 'staff_profile_id' => $teacher,
            'weekly_slots' => [['weekday' => 1, 'start_time' => '10:00']],
            'duration_minutes' => 25, 'timezone' => 'UTC',
            'starts_on' => CarbonImmutable::now('UTC')->toDateString(), 'reason' => 'بلا صلاحية',
        ])->assertForbidden();
        $this->assertDatabaseCount('schedules', 0);
    }

    private function admin(): User
    {
        return User::factory()->inOrganization($this->organizationId)->create();
    }

    private function student(): StudentProfile
    {
        $profile = StudentProfile::query()->findOrFail(Fixtures::studentProfileId());
        Enrollment::query()->create([
            'organization_id' => $this->organizationId,
            'student_profile_id' => (string) $profile->id,
            'program_id' => $this->programId,
            'status' => EnrollmentStatus::Active,
            'applied_at' => now()->subMonth()->utc(),
            'activated_at' => now()->subMonth()->utc(),
        ]);

        return $profile;
    }

    /** معلم مؤهل ومتاح طوال الأسبوع — كما هي إتاحة معلمي الإنتاج اليوم. */
    private function teacher(): string
    {
        $staffProfileId = Fixtures::staffProfileId();
        Fixtures::qualifyTeacher($staffProfileId, $this->courseId);

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            TeacherAvailability::query()->create([
                'staff_profile_id' => $staffProfileId,
                'weekday' => $weekday,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'timezone' => 'UTC',
                'effective_from' => CarbonImmutable::now('UTC')->subMonths(2)->startOfDay(),
                'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
                'approved_at' => CarbonImmutable::now('UTC')->subMonths(2),
            ]);
        }

        return $staffProfileId;
    }

    private function futureSession(Schedule $schedule, string $staffProfileId): Session
    {
        $start = CarbonImmutable::now('UTC')->addWeeks(2);

        return Session::factory()->create([
            'id' => (string) Str::ulid(),
            'organization_id' => $this->organizationId,
            'schedule_id' => (string) $schedule->getKey(),
            'group_id' => null,
            'course_id' => $this->courseId,
            'staff_profile_id' => $staffProfileId,
            'session_type' => 'individual',
            'scheduled_start' => $start,
            'scheduled_end' => $start->addMinutes((int) $schedule->duration_minutes),
            'status' => SessionStatus::Scheduled,
            'title' => ['ar' => 'حصة', 'en' => 'Session'],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ConsoleSessionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $actor;

    private Group $group;

    private Course $course;

    private StaffProfile $teacher;

    private StudentProfile $student;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 08:00 UTC'));
        $this->withoutVite();
        config(['console.enabled' => true]);
        $this->organization = Organization::factory()->create(['default_timezone' => 'Africa/Cairo', 'week_starts_on' => 'saturday']);
        $this->actor = User::factory()->inOrganization($this->organization->id)->create(['timezone' => 'Europe/Paris']);
        foreach (['admin.panel.access', 'session.view', 'student.view.any', 'schedule.view', 'schedule.manage'] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->id === $this->actor->id);
        }
        $this->actingAs($this->actor);
        $this->program = Program::factory()->create(['organization_id' => $this->organization->id]);
        $level = Level::factory()->create(['program_id' => $this->program->id]);
        $this->course = Course::factory()->create(['organization_id' => $this->organization->id, 'level_id' => $level->id, 'session_mode' => SessionMode::Group]);
        $teacherUser = User::factory()->inOrganization($this->organization->id)->create(['name' => 'معلم التقويم']);
        $this->teacher = StaffProfile::query()->create(['organization_id' => $this->organization->id, 'user_id' => $teacherUser->id, 'staff_code' => 'T-CAL', 'employment_type' => 'contractor', 'hired_at' => '2026-01-01']);
        DB::table('teacher_courses')->insert(['id' => (string) Str::ulid(), 'staff_profile_id' => $this->teacher->id, 'course_id' => $this->course->id, 'qualified_at' => now(), 'qualified_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $studentUser = User::factory()->inOrganization($this->organization->id)->create();
        $this->student = StudentProfile::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $studentUser->id]);
        Enrollment::query()->create(['organization_id' => $this->organization->id, 'student_profile_id' => $this->student->id, 'program_id' => $this->program->id, 'current_level_id' => $level->id, 'status' => EnrollmentStatus::Active, 'applied_at' => now()->subMonth(), 'activated_at' => now()->subWeek()]);
        $this->group = $this->createGroup('GR-CALENDAR');
        GroupMembership::query()->create(['group_id' => $this->group->id, 'student_profile_id' => $this->student->id, 'joined_at' => now()->subDay(), 'status' => MembershipStatus::Active]);
        foreach ([0, 3] as $day) {
            TeacherAvailability::query()->create(['staff_profile_id' => $this->teacher->id, 'weekday' => $day, 'start_time' => '08:00', 'end_time' => '20:00', 'timezone' => 'Europe/Paris', 'effective_from' => '2026-10-01', 'effective_to' => '2026-12-31', 'approval_status' => TeacherAvailabilityApprovalStatus::Approved]);
        }
    }

    public function test_creates_real_group_sessions_and_calendar_preserves_local_time_across_dst(): void
    {
        $strict = Model::preventsSilentlyDiscardingAttributes();
        Model::preventSilentlyDiscardingAttributes();
        try {
            $this->get('/manage/schedules/create?group='.$this->group->id.'&course='.$this->course->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Console/GroupScheduleEditor')->where('schedule.group_id', $this->group->id)->has('groups', 1));
            $this->post('/manage/schedules', $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        } finally {
            Model::preventSilentlyDiscardingAttributes($strict);
        }
        $schedule = Schedule::query()->sole();
        $sessions = Session::query()->where('schedule_id', $schedule->id)->orderBy('scheduled_start')->get();
        $this->assertCount(5, $sessions);
        $this->assertSame('07:00', $sessions->first()->scheduled_start->format('H:i'));
        $this->assertSame('08:00', $sessions[2]->scheduled_start->format('H:i'));
        $this->assertTrue($sessions->every(fn (Session $session): bool => $session->scheduled_start->setTimezone('Europe/Paris')->format('H:i') === '09:00'));
        $this->assertSame(5, DB::table('session_participants')->where('student_profile_id', $this->student->id)->count());
        $this->assertDatabaseHas('audit_log', ['action' => 'scheduling.schedule_created', 'auditable_id' => $schedule->id]);
        $this->get('/manage/sessions?date=2026-10-25')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Console/Sessions')->where('from', '2026-10-24')->where('days.0.weekday', 6)->where('school.timezone', 'Africa/Cairo')->where('sessions.0.start', '09:00')->where('filters.timezone', 'Europe/Paris')->has('schedules', 1));
        $this->get('/manage/sessions?view=day&date=2026-10-25&timezone=Africa%2FCairo')->assertOk()->assertInertia(fn (Assert $page) => $page->has('days', 1)->where('sessions.0.start', '11:00'));
        $this->get('/manage/sessions?view=list&date=2026-10-25&group='.$this->group->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('list.data', 1));
    }

    public function test_update_preserves_past_attendance_and_protected_sessions_then_rolls_back_on_conflict(): void
    {
        $data = [...$this->payload(), 'starts_on' => '2026-10-11'];
        $this->post('/manage/schedules', $data)->assertSessionHasNoErrors();
        $schedule = Schedule::query()->sole();
        $past = Session::query()->where('schedule_id', $schedule->id)->orderBy('scheduled_start')->firstOrFail();
        $attendance = Attendance::query()->create(['session_participant_id' => DB::table('session_participants')->where('session_id', $past->id)->where('student_profile_id', $this->student->id)->value('id'), 'status' => AttendanceStatus::Present, 'derived_status' => AttendanceStatus::Present, 'attended_minutes' => 60, 'confirmed_at' => now(), 'confirmed_by' => $this->actor->id]);
        $pastSnapshot = $past->getRawOriginal();
        $attendanceSnapshot = $attendance->fresh()->getRawOriginal();
        $this->travelTo(CarbonImmutable::parse('2026-10-17 08:00 UTC'));
        $protected = Session::query()->where('schedule_id', $schedule->id)->whereDate('scheduled_start', '2026-10-18')->firstOrFail();
        $protectedSnapshot = $protected->getRawOriginal();
        $this->patch('/manage/schedules/'.$schedule->id, [...$data, 'start_time' => '10:00'])->assertSessionHasNoErrors();
        $this->assertSame('10:00:00', $schedule->fresh()->start_time);
        $this->assertSame($pastSnapshot, $past->fresh()->getRawOriginal());
        $this->assertSame($attendanceSnapshot, $attendance->fresh()->getRawOriginal());
        $this->assertSame($protectedSnapshot, $protected->fresh()->getRawOriginal());
        $this->assertDatabaseHas('sessions', ['schedule_id' => $schedule->id, 'status' => SessionStatus::Superseded->value]);
        $this->get('/manage/sessions?date=2026-10-25')->assertInertia(fn (Assert $page) => $page->has('sessions', 1)->where('sessions.0.start', '10:00'));
        $this->get('/manage/sessions?date=2026-10-25&history=1')->assertInertia(fn (Assert $page) => $page->has('sessions', 2));
        $other = $this->createGroup('GR-CONFLICT');
        $this->post('/manage/schedules', [...$data, 'group_id' => $other->id, 'starts_on' => '2026-10-25', 'start_time' => '12:00'])->assertSessionHasNoErrors();
        $snapshot = $schedule->fresh()->getRawOriginal();
        $sessionSnapshot = Session::query()->where('schedule_id', $schedule->id)->orderBy('id')->get()->map->getRawOriginal()->all();
        $auditCount = DB::table('audit_log')->count();
        $this->patch('/manage/schedules/'.$schedule->id, [...$data, 'start_time' => '12:00'])->assertSessionHasErrors('form');
        $this->assertSame($snapshot, $schedule->fresh()->getRawOriginal());
        $this->assertSame($sessionSnapshot, Session::query()->where('schedule_id', $schedule->id)->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($auditCount, DB::table('audit_log')->count());
    }

    public function test_permission_boundaries_foreign_resources_and_invalid_browser_payload_are_rejected(): void
    {
        $this->post('/manage/schedules', $this->payload())->assertSessionHasNoErrors();
        $schedule = Schedule::query()->sole();
        Gate::define('schedule.manage', static fn (): bool => false);
        $this->get('/manage/schedules/'.$schedule->id.'/edit')->assertForbidden();
        $this->post('/manage/schedules', $this->payload())->assertForbidden();
        $this->patch('/manage/schedules/'.$schedule->id, $this->payload())->assertForbidden();
        Gate::define('schedule.manage', static fn (): bool => true);
        Gate::define('student.view.any', static fn (): bool => false);
        $this->get('/manage/sessions')->assertForbidden();
        Gate::define('student.view.any', static fn (): bool => true);
        $this->get('/manage/sessions?group='.(string) Str::ulid())->assertNotFound();
        $this->get('/manage/sessions?teacher='.(string) Str::ulid())->assertNotFound();
        $this->get('/manage/schedules/'.(string) Str::ulid().'/edit')->assertNotFound();
        $this->patch('/manage/schedules/'.$schedule->id, [...$this->payload(), 'course_id' => (string) Str::ulid()])->assertSessionHasErrors('form');
        $this->post('/manage/schedules', [...$this->payload(), 'organization_id' => (string) Str::ulid(), 'student_profile_id' => $this->student->id, 'weekly_slots' => [['weekday' => 0, 'start_time' => '12:00']]])->assertSessionHasErrors(['organization_id', 'student_profile_id', 'weekly_slots']);
        $this->post('/manage/schedules', [...$this->payload(), 'weekdays' => [0, 0], 'ends_on' => '2026-01-01'])->assertSessionHasErrors(['weekdays.0', 'ends_on']);
        $foreignOrg = Organization::factory()->create();
        $foreign = User::factory()->inOrganization($foreignOrg->id)->create();
        $this->actingAs($foreign);
        foreach (['admin.panel.access', 'schedule.manage', 'student.view.any', 'session.view'] as $ability) {
            Gate::define($ability, static fn (): bool => true);
        }
        $this->get('/manage/schedules/'.$schedule->id.'/edit')->assertNotFound();
        $this->patch('/manage/schedules/'.$schedule->id, $this->payload())->assertNotFound();
        $this->get('/manage/sessions?group='.$this->group->id)->assertNotFound();
        $this->get('/manage/sessions?date=2026-10-25')->assertOk()->assertInertia(fn (Assert $page) => $page->has('sessions', 0)->has('schedules', 0));
    }

    public function test_availability_uses_owned_schedule_and_filter_limit_is_explicit(): void
    {
        $this->post('/manage/schedules', $this->payload())->assertSessionHasNoErrors();
        $schedule = Schedule::query()->sole();
        $query = '/manage/schedules/availability?'.http_build_query($this->payload());
        $result = $this->getJson($query)->assertOk();
        $this->assertNotContains('09:00', $result->json('available_start_times'));
        $result = $this->getJson($query.'&schedule_id='.$schedule->id)->assertOk();
        $this->assertContains('09:00', $result->json('available_start_times'));
        $this->getJson($query.'&schedule_id='.(string) Str::ulid())->assertNotFound();
        $this->post('/manage/schedules', [...$this->payload(), 'weekdays' => [3], 'start_time' => '14:00'])->assertSessionHasNoErrors();
        config(['console.directory_search_limit' => 1]);
        $this->get('/manage/sessions?date=2026-10-25')->assertInertia(fn (Assert $page) => $page->where('limited', true)->has('sessions', 1));
    }

    public function test_start_date_uses_schedule_timezone_instead_of_utc_calendar_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 01:00 UTC'));
        $data = [...$this->payload(), 'timezone' => 'Pacific/Pago_Pago', 'weekdays' => [5], 'start_time' => '20:00', 'starts_on' => '2026-10-09', 'ends_on' => '2026-10-09'];
        $this->post('/manage/schedules', $data)->assertSessionHasNoErrors();
        $session = Session::query()->sole();
        $this->assertSame('2026-10-10 07:00', $session->scheduled_start->format('Y-m-d H:i'));
        $this->get('/manage/sessions?view=day&date=2026-10-09&timezone=Pacific%2FPago_Pago')->assertInertia(fn (Assert $page) => $page->where('sessions.0.date', '2026-10-09')->where('sessions.0.start', '20:00'));
    }

    public function test_individual_calendar_identifies_the_student_and_only_links_quran_for_its_actual_course(): void
    {
        $course = Course::factory()->create(['organization_id' => $this->organization->id, 'level_id' => $this->course->level_id, 'session_mode' => SessionMode::Individual, 'code' => config('scheduling.individual_quran.course_code')]);
        $session = Session::factory()->create(['organization_id' => $this->organization->id, 'course_id' => $course->id, 'staff_profile_id' => $this->teacher->id, 'group_id' => null, 'session_type' => 'individual', 'scheduled_start' => '2026-10-25 10:00:00 UTC', 'scheduled_end' => '2026-10-25 10:35:00 UTC']);
        SessionParticipant::query()->create(['session_id' => $session->id, 'join_url_token' => Str::random(48), 'invited_at' => now(), 'student_profile_id' => $this->student->id, 'enrollment_id' => Enrollment::query()->where('student_profile_id', $this->student->id)->value('id')]);
        $this->get('/manage/sessions?date=2026-10-25')->assertInertia(fn (Assert $page) => $page->has('sessions', 1)->where('sessions.0.students.0.id', $this->student->id)->where('sessions.0.students.0.name', User::query()->findOrFail($this->student->user_id)->name)->where('sessions.0.is_quran', true));
        $course->update(['code' => 'OTHER-INDIVIDUAL']);
        $this->get('/manage/sessions?date=2026-10-25')->assertInertia(fn (Assert $page) => $page->where('sessions.0.is_quran', false)->has('sessions.0.students', 1));
    }

    private function createGroup(string $code): Group
    {
        $group = Group::query()->create(['organization_id' => $this->organization->id, 'code' => $code, 'name' => ['ar' => 'مجموعة '.$code], 'capacity' => 12, 'timezone' => 'Europe/Paris', 'status' => GroupStatus::Active, 'starts_on' => '2026-10-01']);
        GroupProgram::query()->create(['group_id' => $group->id, 'program_id' => $this->program->id]);
        GroupTeacher::query()->create(['group_id' => $group->id, 'staff_profile_id' => $this->teacher->id, 'course_id' => $this->course->id, 'role' => GroupTeacherRole::Lead, 'assigned_from' => '2026-10-01']);

        return $group;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['group_id' => $this->group->id, 'course_id' => $this->course->id, 'staff_profile_id' => $this->teacher->id, 'weekdays' => [0], 'interval_weeks' => 1, 'start_time' => '09:00', 'duration_minutes' => 60, 'timezone' => 'Europe/Paris', 'starts_on' => '2026-10-18', 'ends_on' => '2026-11-15'];
    }
}

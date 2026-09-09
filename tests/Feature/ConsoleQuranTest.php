<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ConsoleQuranTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $actor;

    private Program $program;

    private Level $level;

    private Course $course;

    private StaffProfile $teacher;

    private StudentProfile $student;

    public function test_pending_teacher_is_visible_and_filterable_without_a_fabricated_schedule(): void
    {
        $link = PendingTeachingAssignment::query()->create([
            'organization_id' => $this->organization->id, 'student_profile_id' => $this->student->id,
            'staff_profile_id' => $this->teacher->id, 'course_id' => $this->course->id,
            'created_by' => $this->actor->id, 'session_type' => 'individual',
            'duration_minutes' => 25, 'reason' => 'Awaiting approved time',
        ]);
        $this->get('/manage/quran?teacher='.$this->teacher->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)
                ->where('students.data.0.schedule', null)
                ->where('students.data.0.pending_teacher_ids', [$this->teacher->id]));
        $this->assertDatabaseCount('schedules', 0);
        $link->delete();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 08:00:00 UTC'));
        config(['console.enabled' => true]);
        $this->withoutVite();
        $this->organization = Organization::factory()->create(['default_timezone' => 'Africa/Cairo']);
        $this->actor = User::factory()->inOrganization($this->organization->id)->create();
        foreach (['admin.panel.access', 'student.view.any', 'schedule.manage'] as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->id === $this->actor->id);
        }
        $this->actingAs($this->actor);
        $this->program = Program::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true]);
        $this->level = Level::factory()->create(['program_id' => $this->program->id]);
        $this->course = Course::factory()->create([
            'organization_id' => $this->organization->id, 'level_id' => $this->level->id,
            'code' => config('scheduling.individual_quran.course_code'), 'session_mode' => SessionMode::Individual, 'is_active' => true,
        ]);
        $teacherUser = User::factory()->inOrganization($this->organization->id)->create(['name' => 'معلم القرآن']);
        $this->teacher = StaffProfile::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $teacherUser->id, 'staff_code' => 'Q-TEACHER', 'employment_type' => 'contractor', 'hired_at' => '2026-01-01', 'terminated_at' => null,
        ]);
        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(), 'staff_profile_id' => $this->teacher->id, 'course_id' => $this->course->id,
            'qualified_at' => now(), 'qualified_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([0, 3] as $weekday) {
            TeacherAvailability::query()->create([
                'staff_profile_id' => $this->teacher->id, 'weekday' => $weekday,
                'start_time' => '09:00', 'end_time' => '18:00', 'timezone' => 'UTC',
                'effective_from' => '2026-10-01', 'effective_to' => '2026-12-31',
                'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
            ]);
        }
        $this->student = $this->newStudent('طالب القرآن');
    }

    public function test_saves_individual_placement_with_different_day_times_and_keeps_complete_details_visible(): void
    {
        $response = $this->postJson('/manage/quran/'.$this->student->id, $this->payload());
        $response->assertOk()->assertJsonPath('schedule.duration_minutes', 35)->assertJsonPath('schedule.weekly_slots.1.start_time', '12:00');
        $schedule = Schedule::query()->where('student_profile_id', $this->student->id)->firstOrFail();
        $this->assertDatabaseHas('schedule_weekly_slots', ['schedule_id' => $schedule->id, 'weekday' => 0, 'start_time' => '09:00:00']);
        $this->assertDatabaseHas('schedule_weekly_slots', ['schedule_id' => $schedule->id, 'weekday' => 3, 'start_time' => '12:00:00']);
        $this->assertSame(2, Session::query()->where('schedule_id', $schedule->id)->count());
        $this->assertDatabaseHas('audit_log', ['action' => 'scheduling.schedule_created', 'actor_id' => $this->actor->id]);
        $this->get('/manage/quran?status=assigned')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/Quran')->has('students.data', 1)->where('counts.assigned', 1)
            ->where('students.data.0.schedule.duration_minutes', 35)
            ->where('students.data.0.schedule.interval_weeks', 1)
            ->where('students.data.0.schedule.starts_on', '2026-10-11')
            ->where('students.data.0.schedule.ends_on', '2026-10-14')
            ->where('students.data.0.schedule.timezone', 'UTC')
            ->has('students.data.0.schedule.weekly_slots', 2)
            ->where('defaults.timezone', 'Africa/Cairo'));
    }

    public function test_prevents_duplicate_student_placement_and_teacher_conflicts(): void
    {
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())->assertOk();
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('placement');
        $second = $this->newStudent('طالب ثان');
        $this->postJson('/manage/quran/'.$second->id, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('placement');
        $this->assertSame(1, Schedule::query()->count());
        $this->assertDatabaseMissing('schedules', ['student_profile_id' => $second->id]);
    }

    public function test_rejects_foreign_student_and_client_owned_organization(): void
    {
        $other = Organization::factory()->create();
        $foreignUser = User::factory()->inOrganization($other->id)->create();
        $foreignStudent = StudentProfile::factory()->create(['organization_id' => $other->id, 'user_id' => $foreignUser->id]);
        $this->postJson('/manage/quran/'.$foreignStudent->id, $this->payload())->assertNotFound();
        $this->postJson('/manage/quran/'.$this->student->id, [...$this->payload(), 'organization_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('organization_id');
        $this->get('/manage/quran?status=all')->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.id', $this->student->id));
    }

    public function test_separates_view_permission_from_placement_permission_and_filters_pending_students(): void
    {
        $this->newStudent('طالب قابل للبحث');
        $this->get('/manage/quran?search='.urlencode('قابل للبحث'))->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.name', 'طالب قابل للبحث'));
        Gate::define('schedule.manage', static fn (): bool => false);
        $this->get('/manage/quran')->assertOk()->assertInertia(fn (Assert $page) => $page->where('canPlace', false));
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())->assertForbidden();
        Gate::define('student.view.any', static fn (): bool => false);
        $this->get('/manage/quran')->assertForbidden();
    }

    public function test_returns_eligible_availability_and_rechecks_it_on_save(): void
    {
        $query = $this->payload();
        unset($query['weekly_slots']);
        $query['weekdays'] = [0];
        $this->getJson('/manage/quran/availability?'.http_build_query($query))->assertOk()->assertJsonPath('has_declared_availability', true)->assertJsonFragment(['09:00']);
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())->assertOk();
        $result = $this->getJson('/manage/quran/availability?'.http_build_query($query))->assertOk();
        $this->assertNotContains('09:00', $result->json('available_start_times'));
    }

    public function test_edits_existing_schedule_with_distinct_times_and_preserves_past_attendance(): void
    {
        $this->postJson('/manage/quran/'.$this->student->id, [...$this->payload(), 'ends_on' => '2026-10-28'])->assertOk();
        $schedule = Schedule::query()->firstOrFail();
        $first = Session::query()->where('schedule_id', $schedule->id)->orderBy('scheduled_start')->firstOrFail();
        $participant = SessionParticipant::query()->where('session_id', $first->id)->firstOrFail();
        $attendance = Attendance::query()->create([
            'session_participant_id' => $participant->id, 'status' => AttendanceStatus::Present,
            'derived_status' => AttendanceStatus::Present, 'attended_minutes' => 35,
            'joined_after_minutes' => 0, 'left_before_minutes' => 0, 'confirmed_at' => now(), 'confirmed_by' => $this->actor->id,
        ]);
        $beforeAttendance = $attendance->fresh()->getRawOriginal();
        $firstSnapshot = $first->getRawOriginal();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 08:00:00 UTC'));
        $protected = Session::query()->where('schedule_id', $schedule->id)->where('scheduled_start', '2026-10-14 12:00:00')->firstOrFail();
        $this->patchJson('/manage/quran/'.$this->student->id.'/schedules/'.$schedule->id, [
            ...$this->payload(), 'ends_on' => '2026-10-28', 'timezone' => 'Africa/Cairo',
            'weekly_slots' => [['weekday' => 0, 'start_time' => '14:00'], ['weekday' => 3, 'start_time' => '16:00']],
        ])->assertOk()->assertJsonPath('schedule.weekly_slots.1.start_time', '16:00')->assertJsonPath('schedule.timezone', 'Africa/Cairo');
        $this->assertSame($firstSnapshot, $first->fresh()->getRawOriginal());
        $this->assertSame($beforeAttendance, $attendance->fresh()->getRawOriginal());
        $this->assertSame($protected->getRawOriginal(), $protected->fresh()->getRawOriginal());
        $this->assertDatabaseHas('sessions', ['schedule_id' => $schedule->id, 'scheduled_start' => '2026-10-18 11:00:00', 'status' => 'scheduled']);
        $this->assertDatabaseHas('sessions', ['schedule_id' => $schedule->id, 'scheduled_start' => '2026-10-21 13:00:00', 'status' => 'scheduled']);
        $this->assertDatabaseHas('audit_log', ['action' => 'scheduling.schedule_updated', 'actor_id' => $this->actor->id, 'reason' => __('console_quran.audit_update')]);
    }

    public function test_update_rolls_back_superseded_sessions_slots_and_audit_when_teacher_conflicts(): void
    {
        $firstPayload = [...$this->payload(), 'ends_on' => '2026-10-28'];
        $this->postJson('/manage/quran/'.$this->student->id, $firstPayload)->assertOk();
        $schedule = Schedule::query()->where('student_profile_id', $this->student->id)->firstOrFail();
        $other = $this->newStudent('طالب له موعد آخر');
        $this->postJson('/manage/quran/'.$other->id, [...$firstPayload, 'weekly_slots' => [['weekday' => 0, 'start_time' => '11:00'], ['weekday' => 3, 'start_time' => '14:00']]])->assertOk();
        $beforeSessions = Session::query()->where('schedule_id', $schedule->id)->orderBy('id')->get()->map->getRawOriginal()->all();
        $beforeSlots = DB::table('schedule_weekly_slots')->where('schedule_id', $schedule->id)->orderBy('weekday')->get()->toJson();
        $this->patchJson('/manage/quran/'.$this->student->id.'/schedules/'.$schedule->id, [...$firstPayload, 'weekly_slots' => [['weekday' => 0, 'start_time' => '11:00'], ['weekday' => 3, 'start_time' => '14:00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('placement');
        $this->assertSame($beforeSessions, Session::query()->where('schedule_id', $schedule->id)->orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame($beforeSlots, DB::table('schedule_weekly_slots')->where('schedule_id', $schedule->id)->orderBy('weekday')->get()->toJson());
        $this->assertDatabaseMissing('audit_log', ['action' => 'scheduling.schedule_updated']);
    }

    public function test_update_and_edit_availability_require_exact_owned_student_schedule_and_permission(): void
    {
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())->assertOk();
        $schedule = Schedule::query()->firstOrFail();
        $other = $this->newStudent('طالب مختلف');
        $url = '/manage/quran/'.$this->student->id.'/schedules/'.$schedule->id;
        $this->patchJson('/manage/quran/'.$other->id.'/schedules/'.$schedule->id, $this->payload())->assertNotFound();
        $query = $this->payload();
        unset($query['weekly_slots']);
        $query = [...$query, 'weekdays' => [0], 'student_id' => $this->student->id, 'schedule_id' => $schedule->id];
        $this->getJson('/manage/quran/availability?'.http_build_query($query))->assertOk()->assertJsonFragment(['09:00']);
        $this->getJson('/manage/quran/availability?'.http_build_query([...$query, 'student_id' => $other->id]))->assertNotFound();
        $this->patchJson($url, [...$this->payload(), 'course_id' => (string) Str::ulid()])->assertUnprocessable()->assertJsonValidationErrors('course_id');
        $foreignOrg = Organization::factory()->create();
        $foreignActor = User::factory()->inOrganization($foreignOrg->id)->create();
        foreach (['admin.panel.access', 'student.view.any', 'schedule.manage'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
        $this->actingAs($foreignActor)->patchJson($url, $this->payload())->assertNotFound();
        $this->actingAs($this->actor);
        Gate::define('schedule.manage', static fn (): bool => false);
        $this->patchJson($url, $this->payload())->assertForbidden();
        $this->getJson('/manage/quran/availability?'.http_build_query($query))->assertForbidden();
    }

    public function test_workspace_phases_show_real_sessions_attendance_and_progress_without_private_notes(): void
    {
        foreach (['session.view', 'attendance.view', 'session_report.view', 'enrollment.view'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())->assertOk();
        $session = Session::query()->orderBy('scheduled_start')->firstOrFail();
        $participant = SessionParticipant::query()->where('session_id', $session->id)->firstOrFail();
        Attendance::query()->create([
            'session_participant_id' => $participant->id, 'status' => AttendanceStatus::Absent,
            'derived_status' => AttendanceStatus::Absent, 'attended_minutes' => 0,
            'joined_after_minutes' => 0, 'left_before_minutes' => 0,
        ]);
        SessionReport::query()->create([
            'session_id' => $session->id, 'staff_profile_id' => $this->teacher->id, 'topics_covered' => 'مراجعة سورة الملك',
            'supervisor_private_note' => 'PRIVATE-NOTE-MUST-NOT-LEAK', 'submitted_at' => now(), 'is_late' => false,
        ]);
        foreach (['before', 'during', 'after'] as $phase) {
            $response = $this->get('/manage/quran?phase='.$phase.'&tab=students&period=2026-10');
            $response->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('filters.phase', $phase)->where('counts.issues', 1)
                ->where('directory.0.insight.absences', 1)->where('directory.0.insight.progress.topics', 'مراجعة سورة الملك')
                ->where('sessions.total', 2)->where('sessions.data.0.start', '2026-10-11 12:00'));
            $response->assertDontSee('PRIVATE-NOTE-MUST-NOT-LEAK');
        }
        Gate::define('attendance.view', static fn (): bool => false);
        Gate::define('session_report.view', static fn (): bool => false);
        $this->get('/manage/quran?phase=after')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('directory.0.insight.recorded', 0)->where('directory.0.insight.progress', null)->where('can.attendance', false));
    }

    public function test_update_rejects_a_student_archived_or_account_stopped_after_placement(): void
    {
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())->assertOk();
        $schedule = Schedule::query()->firstOrFail();
        $url = '/manage/quran/'.$this->student->id.'/schedules/'.$schedule->id;
        User::query()->whereKey($this->student->user_id)->update(['status' => UserStatus::Frozen]);
        $this->patchJson($url, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('placement');
        User::query()->whereKey($this->student->user_id)->update(['status' => UserStatus::Active]);
        $this->student->delete();
        $this->patchJson($url, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('placement');
    }

    public function test_availability_is_immediately_usable_for_placement_without_approval_permission(): void
    {
        config()->set('scheduling.availability.teacher_requires_approval', false);
        foreach (['staff.view', 'staff.view.any', 'staff.availability.create', 'staff.contract.update'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
        Gate::define('staff.availability.approve', static fn (): bool => false);
        $url = '/manage/teachers/'.$this->teacher->id.'/availability';
        $data = ['weekday' => 1, 'start_time' => '10:00', 'end_time' => '15:00', 'timezone' => 'UTC', 'effective_from' => '2026-10-01', 'effective_to' => '2026-12-31'];
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $slot = TeacherAvailability::query()->where('staff_profile_id', $this->teacher->id)->where('weekday', 1)->firstOrFail();
        $this->assertSame(TeacherAvailabilityApprovalStatus::Approved, $slot->approval_status);
        $this->assertNull($slot->approved_by);
        $query = [...$this->payload(), 'weekdays' => [1]];
        unset($query['weekly_slots']);
        $this->getJson('/manage/quran/availability?'.http_build_query($query))->assertOk()->assertJsonFragment(['10:00']);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('teacher.approval_required', false)
            ->where('teacher.slots.1.can_decide', false));
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('availability');
        $this->assertDatabaseHas('audit_log', ['action' => 'staff.availability_set', 'auditable_id' => $slot->id, 'actor_id' => $this->actor->id]);
        $this->assertDatabaseMissing('audit_log', ['action' => 'staff.availability_decided', 'auditable_id' => $slot->id]);
        $this->delete($url.'/'.$slot->id)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('teacher_availability', ['id' => $slot->id]);
    }

    public function test_availability_editor_creates_pending_then_approves_and_preserves_approved_windows(): void
    {
        config()->set('scheduling.availability.teacher_requires_approval', true);
        foreach (['staff.view', 'staff.view.any', 'staff.availability.create', 'staff.availability.approve', 'staff.contract.update'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
        $url = '/manage/teachers/'.$this->teacher->id.'/availability';
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Console/QuranAvailability')->where('teacher.name', 'معلم القرآن')->has('teacher.slots', 2));
        $data = ['weekday' => 1, 'start_time' => '10:00', 'end_time' => '15:00', 'timezone' => 'UTC', 'effective_from' => '2026-10-01', 'effective_to' => '2026-12-31'];
        $this->post($url, $data)->assertRedirect();
        $slot = TeacherAvailability::query()->where('staff_profile_id', $this->teacher->id)->where('weekday', 1)->firstOrFail();
        $this->assertSame(TeacherAvailabilityApprovalStatus::Pending, $slot->approval_status);
        $query = [...$this->payload(), 'weekdays' => [1]];
        unset($query['weekly_slots']);
        $this->getJson('/manage/quran/availability?'.http_build_query($query))->assertOk()->assertJsonPath('available_start_times', []);
        $this->post($url.'/'.$slot->id.'/decision', ['decision' => 'approved'])->assertRedirect();
        $this->assertSame(TeacherAvailabilityApprovalStatus::Approved, $slot->fresh()->approval_status);
        $this->getJson('/manage/quran/availability?'.http_build_query($query))->assertOk()->assertJsonFragment(['10:00']);
        $this->assertDatabaseHas('audit_log', ['action' => 'staff.availability_set', 'auditable_id' => $slot->id, 'actor_id' => $this->actor->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'staff.availability_decided', 'auditable_id' => $slot->id, 'actor_id' => $this->actor->id]);
        $this->deleteJson($url.'/'.$slot->id)->assertUnprocessable()->assertJsonValidationErrors('availability');
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('availability');
        $this->assertDatabaseHas('teacher_availability', ['id' => $slot->id, 'approval_status' => 'approved']);
    }

    public function test_availability_editor_scopes_teacher_window_and_each_write_permission(): void
    {
        config()->set('scheduling.availability.teacher_requires_approval', true);
        foreach (['staff.view', 'staff.view.any', 'staff.availability.create', 'staff.availability.approve', 'staff.contract.update'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
        $url = '/manage/teachers/'.$this->teacher->id.'/availability';
        $data = ['weekday' => 1, 'start_time' => '10:00', 'end_time' => '15:00', 'timezone' => 'UTC', 'effective_from' => '2026-10-01', 'effective_to' => null];
        $this->postJson($url, [...$data, 'organization_id' => (string) Str::ulid()])->assertUnprocessable()->assertJsonValidationErrors('organization_id');
        $this->postJson($url, [...$data, 'end_time' => '09:00'])->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->post($url, $data)->assertRedirect();
        $slot = TeacherAvailability::query()->where('staff_profile_id', $this->teacher->id)->where('weekday', 1)->firstOrFail();
        Gate::define('staff.availability.approve', static fn (): bool => false);
        $this->postJson($url.'/'.$slot->id.'/decision', ['decision' => 'approved'])->assertForbidden();
        Gate::define('staff.contract.update', static fn (): bool => false);
        $this->deleteJson($url.'/'.$slot->id)->assertForbidden();
        Gate::define('staff.availability.create', static fn (): bool => false);
        $this->postJson($url, $data)->assertForbidden();
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->inOrganization($otherOrg->id)->create();
        Gate::define('admin.panel.access', static fn (): bool => true);
        $this->actingAs($otherUser)->get($url)->assertNotFound();
        Gate::define('staff.availability.approve', static fn (): bool => true);
        $this->postJson($url.'/'.$slot->id.'/decision', ['decision' => 'approved'])->assertNotFound();
        $this->actingAs($this->actor);
        $this->post($url.'/'.$slot->id.'/decision', ['decision' => 'rejected'])->assertRedirect();
        Gate::define('staff.contract.update', static fn (): bool => true);
        $this->delete($url.'/'.$slot->id)->assertRedirect();
        $this->assertDatabaseMissing('teacher_availability', ['id' => $slot->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'staff.availability_removed', 'auditable_id' => $slot->id]);
    }

    public function test_new_quran_acceptance_reaches_placement_and_enrollment_rolls_back_on_conflict(): void
    {
        $this->seed(AccessControlSeeder::class);
        foreach (['student.create', 'student.view', 'enrollment.create'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
        Gate::define('group.manage', static fn (): bool => false);
        $user = User::factory()->inOrganization($this->organization->id)->create(['name' => 'طلب قرآن جديد']);
        $this->seed(GeographySeeder::class);
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        $region = $geography->regionsOf($country->id)[0];
        $application = RegistrationApplication::query()->create([
            'organization_id' => $this->organization->id, 'full_name' => 'طلب قرآن جديد', 'date_of_birth' => '2010-01-01',
            'gender' => 'male', 'country_id' => $country->id, 'region_id' => $region->id, 'email' => $user->email,
            'status' => RegistrationStatus::Submitted, 'submitted_at' => now(),
            'preferred_program_id' => $this->program->id, 'preferred_course_id' => $this->course->id,
        ]);
        $this->post('/manage/registration/applications/'.$application->id.'/decision', [
            'decision' => 'accept', 'account_mode' => 'existing', 'existing_user_id' => $user->id, 'identity_confirmed' => true, 'timezone' => 'Africa/Cairo',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $studentId = $application->fresh()->student_profile_id;
        $this->assertNotNull($studentId);
        $link = '/manage/quran?'.http_build_query(['student' => $studentId, 'application' => $application->id, 'tab' => 'students']);
        $this->get('/manage/registration/applications/'.$application->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('placementUrl', $link));
        $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('counts.pending', 2)->has('directory', 2));
        $this->assertDatabaseMissing('enrollments', ['student_profile_id' => $studentId]);
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())->assertOk();
        $this->postJson('/manage/quran/'.$studentId, [...$this->payload(), 'application_id' => $application->id])->assertUnprocessable();
        $this->assertDatabaseMissing('enrollments', ['student_profile_id' => $studentId]);
        $this->assertDatabaseMissing('schedules', ['student_profile_id' => $studentId]);
        $this->assertSame(RegistrationStatus::WaitingAssignment, $application->fresh()->status);
        $data = [...$this->payload(), 'application_id' => $application->id, 'weekly_slots' => [['weekday' => 0, 'start_time' => '11:00'], ['weekday' => 3, 'start_time' => '14:00']]];
        $this->postJson('/manage/quran/'.$studentId, $data)->assertOk();
        $this->assertSame(1, Enrollment::query()->where('student_profile_id', $studentId)->count());
        $this->assertSame(RegistrationStatus::Assigned, $application->fresh()->status);
        $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('counts.assigned', 2));
        $this->postJson('/manage/quran/'.$studentId, $data)->assertUnprocessable();
        $this->assertSame(1, Enrollment::query()->where('student_profile_id', $studentId)->count());
    }

    public function test_quran_placement_marks_only_selected_application_and_requires_admission_permission(): void
    {
        Gate::define('student.view', static fn (): bool => true);
        $this->seed(GeographySeeder::class);
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        $old = RegistrationApplication::query()->create([
            'organization_id' => $this->organization->id, 'full_name' => 'طلب قرآن سابق', 'date_of_birth' => '2010-01-01',
            'gender' => 'male', 'country_id' => $country->id, 'region_id' => $geography->regionsOf($country->id)[0]->id, 'student_profile_id' => $this->student->id, 'user_id' => $this->student->user_id,
            'status' => RegistrationStatus::Assigned, 'submitted_at' => now()->subMonth(),
            'preferred_program_id' => $this->program->id, 'preferred_course_id' => $this->course->id,
        ]);
        $application = $old->replicate();
        $application->status = RegistrationStatus::WaitingAssignment;
        $application->save();
        $snapshot = $old->fresh()->getRawOriginal();
        $data = [...$this->payload(), 'application_id' => $application->id];
        Gate::define('enrollment.create', static fn (): bool => false);
        $this->postJson('/manage/quran/'.$this->student->id, $data)->assertForbidden();
        Gate::define('enrollment.create', static fn (): bool => true);
        $this->postJson('/manage/quran/'.$this->student->id, [...$data, 'application_id' => (string) Str::ulid()])->assertNotFound();
        $this->postJson('/manage/quran/'.$this->student->id, $data)->assertOk();
        $this->assertSame($snapshot, $old->fresh()->getRawOriginal());
        $this->assertSame(RegistrationStatus::Assigned, $application->fresh()->status);
        $this->assertSame(1, Enrollment::query()->where('student_profile_id', $this->student->id)->count());
    }

    private function newStudent(string $name): StudentProfile
    {
        $user = User::factory()->inOrganization($this->organization->id)->create(['name' => $name]);
        $student = StudentProfile::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id]);
        Enrollment::query()->create([
            'organization_id' => $this->organization->id, 'student_profile_id' => $student->id,
            'program_id' => $this->program->id, 'current_level_id' => $this->level->id,
            'status' => EnrollmentStatus::Active, 'applied_at' => now()->subMonth(), 'activated_at' => now()->subWeek(),
        ]);

        return $student;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'staff_profile_id' => $this->teacher->id,
            'weekly_slots' => [['weekday' => 0, 'start_time' => '09:00'], ['weekday' => 3, 'start_time' => '12:00']],
            'duration_minutes' => 35, 'interval_weeks' => 1, 'timezone' => 'UTC',
            'starts_on' => '2026-10-11', 'ends_on' => '2026-10-14',
        ];
    }
}

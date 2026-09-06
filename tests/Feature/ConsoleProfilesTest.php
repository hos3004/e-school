<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AcademicReports\Domain\Contracts\StudentLearningReportQueries;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Identity\Domain\Models\User;
use Modules\Staff\Domain\Models\StaffProfile;
use Tests\TestCase;

final class ConsoleProfilesTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 9, 6)->setTime(12, 0));
        config(['console.enabled' => true, 'sessions.reporting.max_items' => 1]);
        foreach (['student.view', 'session.view', 'session_report.view'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
    }

    public function test_profile_traverses_equal_time_cursor_pages_and_does_not_count_missing_attendance_as_absence(): void
    {
        $participantId = $this->createSessionParticipant();
        $participant = (array) DB::table('session_participants')->find($participantId);
        $session = (array) DB::table('sessions')->find($participant['session_id']);
        unset($session['time_range']);
        $secondSession = (string) Str::ulid();
        $otherUser = User::factory()->inOrganization($session['organization_id'])->create();
        $otherTeacher = StaffProfile::query()->create(['organization_id' => $session['organization_id'], 'user_id' => $otherUser->id, 'staff_code' => 'STF-'.Str::random(10), 'employment_type' => 'full_time']);
        DB::table('sessions')->insert([...$session, 'id' => $secondSession, 'staff_profile_id' => $otherTeacher->id, 'original_teacher_id' => $otherTeacher->id]);
        $secondParticipant = (string) Str::ulid();
        DB::table('session_participants')->insert([...$participant, 'id' => $secondParticipant, 'session_id' => $secondSession, 'join_url_token' => Str::random(32)]);
        Attendance::factory()->create(['session_participant_id' => $secondParticipant, 'status' => 'absent', 'derived_status' => 'absent', 'attended_minutes' => 0]);
        $student = DB::table('student_profiles')->find($participant['student_profile_id']);
        $actor = User::query()->findOrFail($student->user_id);
        $actor->update(['timezone' => 'Africa/Cairo']);
        $this->actingAs($actor)->get('/learn/student/profile?profile_month=2026-09')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Learning/Profile')
                ->where('timezone', 'Africa/Cairo')->where('profileWorkspace.month', '2026-09')
                ->has('profileWorkspace.sessions', 2)->where('profileWorkspace.counts.recorded', 1)
                ->where('profileWorkspace.counts.absent', 1)->where('profileWorkspace.sessions.0.attendance', null)
                ->missing('hub.guardians')->missing('hub.contracts')->missing('profile.notes'));
        $this->get('/learn/student/profile?profile_month=2026-10')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('profileWorkspace.sessions', 0));
        $this->get('/learn/student/profile?profile_month[]=bad')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('profileWorkspace.month', '2026-09'));
    }

    public function test_teacher_profile_projection_excludes_other_teacher_student_and_supervisor_notes_at_source(): void
    {
        $participantId = $this->createSessionParticipant();
        $participant = (array) DB::table('session_participants')->find($participantId);
        $session = (array) DB::table('sessions')->find($participant['session_id']);
        $org = $session['organization_id'];
        $studentId = $participant['student_profile_id'];
        $teacherId = DB::table('staff_profiles')->where('id', $session['staff_profile_id'])->value('user_id');
        $teacher = User::query()->findOrFail($teacherId);
        DB::table('schedules')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $org, 'course_id' => $session['course_id'],
            'staff_profile_id' => $session['staff_profile_id'], 'student_profile_id' => $studentId,
            'session_type' => 'regular', 'rrule' => 'FREQ=WEEKLY;BYDAY=MO', 'start_time' => '16:00',
            'duration_minutes' => 30, 'timezone' => 'UTC', 'starts_on' => '2026-09-01',
            'materialized_until' => '2026-09-06', 'is_active' => true, 'created_by' => $teacherId,
        ]);
        $reportId = (string) Str::ulid();
        DB::table('session_reports')->insert([
            'id' => $reportId, 'session_id' => $session['id'], 'staff_profile_id' => $session['staff_profile_id'],
            'submitted_at' => now(), 'is_late' => false, 'topics_covered' => 'موضوع الحصة', 'general_notes' => 'PRIVATE WHOLE CLASS',
            'supervisor_private_note' => 'PRIVATE SUPERVISOR', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('session_report_students')->insert([
            'id' => (string) Str::ulid(), 'session_report_id' => $reportId, 'student_profile_id' => $studentId,
            'participation' => 4, 'performance' => 3, 'commitment' => 5, 'note' => 'ملاحظة الطالب المصرح بها',
        ]);
        $foreignParticipant = $this->createSessionParticipant();
        $foreign = DB::table('session_participants')->find($foreignParticipant);
        DB::table('session_report_students')->insert([
            'id' => (string) Str::ulid(), 'session_report_id' => $reportId, 'student_profile_id' => $foreign->student_profile_id,
            'participation' => 1, 'performance' => 1, 'commitment' => 1, 'note' => 'PRIVATE OTHER STUDENT',
        ]);
        $response = $this->actingAs($teacher)->get('/learn/teacher/students/'.$studentId.'?profile_month=2026-09&audience=admin');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->where('own', false)->where('account', null)
            ->missing('profile.email')->missing('profile.phone')->missing('profile.notes')
            ->missing('hub.guardians')->missing('hub.contracts')->missing('hub.account')
            ->has('profileWorkspace.sessions', 1)->has('profileWorkspace.learningReports', 1)
            ->where('profileWorkspace.learningReports.0.note', 'ملاحظة الطالب المصرح بها')
            ->where('profileWorkspace.learningReports.0.performance', 3)
            ->missing('profileWorkspace.learningReports.0.supervisor_private_note'));
        $this->assertStringNotContainsString('PRIVATE SUPERVISOR', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE WHOLE CLASS', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE OTHER STUDENT', $response->getContent());
        $this->assertSame([], app(StudentLearningReportQueries::class)->forStudent(
            $this->organizationId, $studentId, [$session['id']], $session['staff_profile_id'],
        ));
        Gate::define('session_report.view', static fn (): bool => false);
        $this->get('/learn/teacher/students/'.$studentId)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('profileWorkspace.learningReports', 0));
        DB::table('schedules')->where('student_profile_id', $studentId)->update(['is_active' => false]);
        $this->get('/learn/teacher/students/'.$studentId)->assertNotFound();
    }

    public function test_registration_kind_switch_is_limited_to_authorized_creation_routes(): void
    {
        $participantId = $this->createSessionParticipant();
        $participant = DB::table('session_participants')->find($participantId);
        $userId = DB::table('student_profiles')->where('id', $participant->student_profile_id)->value('user_id');
        foreach (['admin.panel.access', 'student.view.any', 'student.create'] as $permission) {
            Gate::define($permission, static fn (): bool => true);
        }
        Gate::define('staff.contract.update', static fn (): bool => false);
        $this->actingAs(User::query()->findOrFail($userId))->get('/manage/students/create')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Console/People/Form')->has('personKinds', 1)
                ->where('personKinds.0.kind', 'students')->where('personKinds.0.label', 'طالب'));
        $this->get('/manage/teachers/create')->assertForbidden();
    }

    public function test_teacher_session_total_counts_group_once_and_attendance_does_not_estimate_payroll(): void
    {
        $participantId = $this->createSessionParticipant();
        $participant = DB::table('session_participants')->find($participantId);
        $session = DB::table('sessions')->find($participant->session_id);
        $teacherId = DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id');
        DB::table('sessions')->where('id', $session->id)->update(['status' => 'completed']);
        $this->actingAs(User::query()->findOrFail($teacherId))->get('/learn/teacher/profile')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('profileWorkspace.sessions', 1)
                ->where('profileWorkspace.counts.completed', 1)->missing('profileWorkspace.payroll')
                ->missing('hub.contracts')->missing('hub.rates'));
    }
}

<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Tests\TestCase;

final class LearningServicesTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake();
        Notification::fake();
        config(['console.enabled' => true, 'features.payroll' => true]);
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
        $this->travelTo(CarbonImmutable::parse('2026-09-10T10:00:00Z'));
    }

    public function test_teacher_creates_real_arabic_assignment_student_submits_and_teacher_grades_with_scoped_permissions(): void
    {
        [$teacher,$student,$session] = $this->fixture();
        DB::table('teacher_courses')->insert(['id' => (string) Str::ulid(), 'staff_profile_id' => $session->staff_profile_id, 'course_id' => $session->course_id, 'qualified_by' => $teacher->id, 'qualified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($teacher, 'web')->get('/learn/teacher/assignments')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/TeachingAssignments')->has('targets', 1));
        $input = ['target' => $session->course_id, 'title' => 'تدريب القراءة', 'instructions' => 'اقرأ النص وأجب', 'due_local' => '2026-09-12T18:00', 'max_score' => 20, 'allows_late' => false, 'late_penalty_percent' => 0, 'staff_profile_id' => 'spoofed'];
        $this->from('/learn/teacher/assignments')->post('/learn/teacher/assignments', $input)->assertRedirect('/learn/teacher/assignments')->assertSessionHasNoErrors();
        $assignment = Assignment::query()->sole();
        $this->assertSame((string) $session->staff_profile_id, (string) $assignment->staff_profile_id);
        $this->assertSame(['ar' => 'تدريب القراءة'], $assignment->title);
        $this->assertSame('2026-09-12 15:00:00', $assignment->due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audit_log', ['action' => 'assignments.created', 'actor_id' => $teacher->id]);
        $this->get('/learn/teacher/assignments/list')->assertOk()->assertJsonPath('data.0.id', $assignment->id);
        $this->get('/learn/teacher/assignments/'.$assignment->id)->assertOk()->assertJsonPath('data.submissions.0.student_name', $student->name);
        $this->actingAs($student, 'web')->from('/learn/student')->post('/learn/student/assignments/'.$assignment->id.'/submit', ['content' => 'إجابة الطالب'])->assertRedirect('/learn/student')->assertSessionHasNoErrors();
        $submission = DB::table('assignment_submissions')->where('assignment_id', $assignment->id)->sole();
        $this->actingAs($teacher, 'web')->postJson('/api/assignment-submissions/'.$submission->id.'/grade', ['score' => 18, 'feedback' => 'أحسنت', 'reason' => 'رصد التسليم'])->assertOk();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'graded', 'score' => 18]);
        $this->postJson('/api/assignment-submissions/'.$submission->id.'/grade', ['score' => 20, 'reason' => 'إعادة'])->assertUnprocessable();
        [$other] = $this->fixture();
        $this->actingAs($other, 'web')->get('/learn/teacher/assignments/'.$assignment->id)->assertNotFound();
        $this->actingAs($other, 'sanctum')->postJson('/api/assignment-submissions/'.$submission->id.'/grade', ['score' => 20, 'reason' => 'اختبار'])->assertForbidden();
        $this->actingAs($other, 'web')->post('/learn/teacher/assignments', $input)->assertForbidden();
        $this->assertSame(1, Assignment::query()->count());
        DB::table('teacher_courses')->where('staff_profile_id', $session->staff_profile_id)->update(['revoked_at' => now(), 'revoked_by' => $teacher->id]);
        $this->actingAs($teacher, 'web')->post('/learn/teacher/assignments', $input)->assertForbidden();
    }

    public function test_postponement_uses_account_timezone_keeps_new_urls_and_rejects_unrelated_teacher(): void
    {
        [$teacher,$student,$session] = $this->fixture();
        $url = '/learn/student/sessions/'.$session->id;
        $this->actingAs($student, 'web')->get($url)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Session')->where('postponementRequestUrl', route('learning.sessions.postpone', ['kind' => 'student', 'session' => $session->id]))->where('canRequestPostponement', true));
        $this->from($url)->post($url.'/postponements', ['category' => 'schedule', 'proposed_local' => '2026-09-12T18:00'])->assertRedirect($url)->assertSessionHasNoErrors();
        $record = DB::table('postponement_requests')->sole();
        $this->assertSame('2026-09-12 15:00:00', CarbonImmutable::parse($record->proposed_start)->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('تعارض في الموعد', $record->reason);
        $this->post($url.'/postponements', ['category' => 'schedule', 'proposed_local' => '2026-09-12T19:00'])->assertSessionHasErrors();
        $this->assertSame(1, DB::table('postponement_requests')->count());
        $this->actingAs($teacher, 'web')->get('/learn/teacher/postponements')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Postponements')->has('requests', 1)->where('requests.0.rejectUrl', route('learning.teacher.postponements.reject', ['postponement' => $record->id])));
        [$other] = $this->fixture();
        $this->actingAs($other, 'web')->post('/learn/teacher/postponements/'.$record->id.'/reject', ['category' => 'schedule'])->assertNotFound();
        $this->actingAs($teacher, 'web')->from('/learn/teacher/postponements')->post('/learn/teacher/postponements/'.$record->id.'/reject', ['category' => 'schedule'])->assertRedirect('/learn/teacher/postponements')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('postponement_requests', ['id' => $record->id, 'status' => 'rejected']);
    }

    public function test_teacher_proposes_alternative_student_accepts_and_makeup_stays_in_new_workspace(): void
    {
        [$teacher, $student, $session] = $this->fixture();
        $url = '/learn/student/sessions/'.$session->id;
        $this->actingAs($student, 'web')->from($url)->post($url.'/postponements', ['category' => 'schedule', 'proposed_local' => '2026-09-12T18:00'])->assertSessionHasNoErrors();
        $record = DB::table('postponement_requests')->sole();
        $this->actingAs($teacher, 'web')->from('/learn/teacher/postponements')->post('/learn/teacher/postponements/'.$record->id.'/propose', ['category' => 'schedule', 'proposed_local' => '2026-09-12T20:00'])->assertRedirect('/learn/teacher/postponements')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('postponement_requests', ['id' => $record->id, 'status' => 'alternative_proposed']);
        $accept = '/learn/student/postponements/'.$record->id.'/accept-alternative';
        $this->actingAs($student, 'web')->get($url)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('postponementRequest.acceptAlternativeUrl', url($accept)));
        $this->from($url)->post($accept)->assertRedirect($url)->assertSessionHasNoErrors();
        $record = DB::table('postponement_requests')->where('id', $record->id)->sole();
        $this->assertSame('scheduled', $record->status);
        $this->assertSame('postponed', $session->fresh()->status->value);
        $makeup = Session::query()->findOrFail($record->makeup_session_id);
        $this->assertSame('2026-09-12 17:00:00', $makeup->scheduled_start->utc()->format('Y-m-d H:i:s'));
        $this->get('/learn/student/sessions/'.$makeup->id)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Session')->where('session.id', $makeup->id));
        $this->post($accept)->assertSessionHasErrors();
        $this->assertSame(2, Session::query()->where('organization_id', $session->organization_id)->count());
    }

    public function test_student_apology_uses_existing_action_and_blocks_foreign_session(): void
    {
        [$teacher,$student,$session,$participant] = $this->fixture();
        $url = '/learn/student/sessions/'.$session->id;
        $this->actingAs($student, 'web')->from($url)->post($url.'/apology', ['category' => 'health'])->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('session_participants', ['id' => $participant, 'excuse_reason' => 'ظرف صحي', 'excused_by' => $student->id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'sessions.student_apology_submitted', 'actor_id' => $student->id]);
        [, $foreign] = $this->fixture();
        $this->actingAs($foreign)->post($url.'/apology', ['category' => 'health'])->assertSessionHasErrors();
        $this->assertDatabaseMissing('session_participants', ['session_id' => $session->id, 'excused_by' => $foreign->id]);
    }

    public function test_teacher_availability_is_immediate_and_only_owner_can_withdraw_without_changing_sessions(): void
    {
        [$teacher,$student,$session] = $this->fixture();
        $this->actingAs($teacher, 'web')->get('/learn/teacher/availability')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Availability')->where('canCreate', true)->where('teacher.approval_required', false));
        $this->from('/learn/teacher/availability')->post('/learn/teacher/availability', ['weekday' => 2, 'start_time' => '18:00', 'end_time' => '20:00', 'timezone' => 'Africa/Cairo', 'effective_from' => '2026-09-10', 'effective_to' => null])->assertRedirect('/learn/teacher/availability')->assertSessionHasNoErrors();
        $slot = DB::table('teacher_availability')->sole();
        $this->assertSame('approved', $slot->approval_status);
        $this->assertNull($slot->approved_by);
        $this->assertSame((string) $session->staff_profile_id, $slot->staff_profile_id);
        [$other] = $this->fixture();
        $this->actingAs($other, 'web')->delete('/learn/teacher/availability/'.$slot->id)->assertNotFound();
        $this->actingAs($teacher, 'web')->delete('/learn/teacher/availability/'.$slot->id)->assertRedirect();
        $this->assertDatabaseMissing('teacher_availability', ['id' => $slot->id]);
        $this->assertDatabaseHas('sessions', ['id' => $session->id, 'staff_profile_id' => $session->staff_profile_id]);
        $this->assertDatabaseHas('audit_log', ['action' => 'staff.availability_removed', 'auditable_id' => $slot->id, 'actor_id' => $teacher->id]);
        $this->actingAs($student, 'web')->get('/learn/teacher/availability')->assertForbidden();
    }

    public function test_services_use_new_theme_and_own_financial_statement_only(): void
    {
        [$teacher,$student,$session] = $this->fixture();
        $this->actingAs($teacher, 'web');
        foreach (['schedule' => 'Schedule', 'notifications' => 'NotificationsPage', 'earnings' => 'Earnings'] as $path => $component) {
            $this->get('/learn/teacher/'.$path)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/'.$component));
        }
        $this->get('/learn/teacher/earnings?staff_profile_id='.$student->id)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->has('periods', 0));
        $this->actingAs($student, 'web');
        foreach (['schedule' => 'Schedule', 'notifications' => 'NotificationsPage', 'reports' => 'Reports'] as $path => $component) {
            $this->get('/learn/student/'.$path)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/'.$component));
        }
        $this->get('/learn/teacher/earnings')->assertForbidden();
        $this->get('/learn/teacher/assignments')->assertForbidden();
        $this->get('/learn/teacher/postponements')->assertForbidden();
        config(['features.payroll' => false]);
        $this->actingAs($teacher, 'web')->get('/learn/teacher/earnings')->assertForbidden();
    }

    /** @return array{User,User,Session,string} */
    private function fixture(): array
    {
        $participant = $this->createSessionParticipant();
        $row = DB::table('session_participants')->where('id', $participant)->sole();
        $session = Session::query()->findOrFail($row->session_id);
        $session->update(['status' => SessionStatus::Scheduled, 'scheduled_start' => now()->addDay(), 'scheduled_end' => now()->addDay()->addHour()]);
        $teacher = User::query()->findOrFail(DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id'));
        $student = User::query()->findOrFail(DB::table('student_profiles')->where('id', $row->student_profile_id)->value('user_id'));
        foreach ([[$teacher, 'teacher'], [$student, 'student']] as [$user,$role]) {
            $user->update(['timezone' => 'Africa/Cairo']);
            $roleId = DB::table('roles')->whereNull('organization_id')->where('guard_name', 'web')->where('name', $role)->value('id');
            DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id]);
        }

        return [$teacher, $student, $session, $participant];
    }
}

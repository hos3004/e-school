<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Discipline\Domain\Events\ReactivationRequested;
use Modules\Discipline\Domain\Events\ReactivationReviewed;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentFrozen;
use Modules\Enrollments\Domain\Events\EnrollmentStatusChanged;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ConsoleFollowupTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = ['admin.panel.access', 'student.view.any', 'enrollment.view', 'attendance.view', 'discipline.view_any', 'enrollment.pause', 'enrollment.freeze', 'enrollment.reactivate', 'discipline.request_reactivation', 'attendance.override', 'discipline.waive_violations'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['console.enabled' => true]);
        $this->travelTo(CarbonImmutable::parse('2026-09-06T08:00:00Z'));
        CarbonImmutable::setTestNow('2026-09-06T08:00:00Z');
        foreach ($this->permissions as $permission) {
            Gate::define($permission, fn (): bool => in_array($permission, $this->permissions, true));
        }
        Event::fake([EnrollmentFrozen::class, EnrollmentStatusChanged::class, ReactivationRequested::class, ReactivationReviewed::class]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        $this->travelBack();
        parent::tearDown();
    }

    public function test_followup_lists_real_absence_and_held_cases_using_the_policy_window_and_scoped_dtos(): void
    {
        [$org,$actor,$enrollment,$student,$course] = $this->context();
        config(['discipline.counter_window_days' => 7]);
        $actor->update(['timezone' => 'Asia/Riyadh']);
        $this->absence($org, $actor, $enrollment, $student, $course, now()->subDays(2)->toISOString());
        $this->absence($org, $actor, $enrollment, $student, $course, now()->subDays(10)->toISOString());
        ViolationEvent::query()->create(['organization_id' => $org->id, 'enrollment_id' => $enrollment->id, 'student_profile_id' => $student->id, 'type' => ViolationType::UnexcusedAbsence, 'occurred_at' => now()->subDay(), 'window_key' => 'R7', 'is_countable' => true]);
        [$foreignOrg,, $foreignEnrollment] = $this->context(EnrollmentStatus::Paused);
        $this->actingAs($actor)->get('/manage/followup')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/Followup')->where('timezone', 'Asia/Riyadh')->where('counts.students', 1)->where('counts.absence', 1)->has('cases.data', 1)
            ->where('cases.data.0.id', (string) $enrollment->id)->where('cases.data.0.absences', 1)->where('cases.data.0.violations', 1));
        $this->get('/manage/followup?enrollment='.$enrollment->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('selected.attendance', 1)->where('selected.attendance.0.status', 'absent')->where('selected.actors.'.$actor->id, $actor->name));
        $this->get('/manage/followup?enrollment='.$foreignEnrollment->id)->assertNotFound();
        $this->post('/manage/followup/'.$foreignEnrollment->id, $this->data('resume', 'paused'))->assertNotFound();
        self::assertSame((string) $foreignOrg->id, (string) $foreignEnrollment->fresh()->organization_id);
    }

    public function test_absence_history_reads_every_report_batch_including_sessions_with_the_same_start(): void
    {
        [$org, $actor, $enrollment, $student, $course] = $this->context();
        config(['sessions.reporting.max_items' => 1]);
        $sameStart = now()->subDays(2)->toISOString();
        $first = $this->absence($org, $actor, $enrollment, $student, $course, $sameStart);
        $otherTeacher = User::factory()->inOrganization((string) $org->id)->create();
        $second = $this->absence($org, $otherTeacher, $enrollment, $student, $course, $sameStart);
        $earliestParticipant = SessionParticipant::query()
            ->whereIn('id', [$first->session_participant_id, $second->session_participant_id])
            ->orderBy('session_id')->firstOrFail();
        Attendance::query()->where('session_participant_id', $earliestParticipant->id)
            ->update(['status' => AttendanceStatus::Present, 'derived_status' => AttendanceStatus::Present]);
        $this->absence($org, $actor, $enrollment, $student, $course, now()->subDay()->toISOString());

        $this->actingAs($actor)->get('/manage/followup?enrollment='.$enrollment->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('counts.students', 1)->where('counts.absence', 1)
            ->has('cases.data', 1)->where('cases.data.0.absences', 2)
            ->has('selected.attendance', 3)
            ->where('selected.attendance', fn ($rows): bool => count(array_unique(array_column($rows->all(), 'id'))) === 3));
    }

    public function test_read_permissions_do_not_allow_a_study_status_change_and_the_feature_can_be_disabled(): void
    {
        [,$actor,$enrollment] = $this->context();
        $this->permissions = ['admin.panel.access', 'student.view.any', 'enrollment.view', 'attendance.view', 'discipline.view_any'];
        $this->actingAs($actor)->get('/manage/followup?enrollment='.$enrollment->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('selected.actions', []));
        $this->post('/manage/followup/'.$enrollment->id, $this->data('freeze', 'active'))->assertForbidden();
        self::assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        $this->permissions = ['admin.panel.access', 'student.view.any', 'enrollment.view'];
        $this->get('/manage/followup')->assertForbidden();
        config(['console.enabled' => false]);
        $this->get('/manage/followup')->assertNotFound();
    }

    public function test_pause_and_resume_are_audited_keep_the_account_and_enforce_the_return_date_policy(): void
    {
        [$org,$actor,$enrollment,$student] = $this->context();
        $this->actingAs($actor);
        $this->post('/manage/followup/'.$enrollment->id, [...$this->data('pause', 'active'), 'return_date' => '2026-09-07'])->assertSessionHasErrors('return_date');
        self::assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        $this->post('/manage/followup/'.$enrollment->id, [...$this->data('pause', 'active'), 'return_date' => '2026-09-20'])->assertSessionHasNoErrors()->assertRedirect();
        self::assertSame(EnrollmentStatus::Paused, $enrollment->fresh()->status);
        self::assertSame('2026-09-20', $enrollment->fresh()->expected_return_date->toDateString());
        $this->get('/manage/followup?enrollment='.$enrollment->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selected.actions.0', 'resume')
                ->where('selected.return_date', '2026-09-20'));
        $this->post('/manage/followup/'.$enrollment->id, $this->data('resume', 'paused'))->assertSessionHasNoErrors();
        self::assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        self::assertNotNull(User::query()->find($student->user_id));
        self::assertSame(2, DB::table('enrollment_status_history')->where('enrollment_id', $enrollment->id)->count());
        self::assertSame(2, DB::table('audit_log')->where('organization_id', $org->id)->where('action', 'enrollments.status_changed')->count());
    }

    public function test_pause_requires_a_return_date_with_an_arabic_message_and_preserves_study_status(): void
    {
        [, $actor, $enrollment] = $this->context();
        $response = $this->actingAs($actor)->postJson('/manage/followup/'.$enrollment->id, $this->data('pause', 'active'))
            ->assertUnprocessable()->assertJsonValidationErrors('return_date');
        $message = $response->json('errors.return_date.0');
        self::assertSame('حدد موعد العودة عند تعليق الدراسة مؤقتًا.', $message);
        self::assertDoesNotMatchRegularExpression('/[A-Za-z_]/', $message);
        self::assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        self::assertSame(0, DB::table('audit_log')->where('auditable_id', $enrollment->id)->count());
    }

    public function test_frozen_return_creates_one_request_and_never_reactivates_directly(): void
    {
        [,$actor,$enrollment] = $this->context(EnrollmentStatus::Frozen);
        $this->actingAs($actor)->post('/manage/followup/'.$enrollment->id, $this->data('request', 'frozen'))->assertSessionHasNoErrors();
        self::assertSame(EnrollmentStatus::ReactivationRequested, $enrollment->fresh()->status);
        self::assertSame(1, ReactivationRequest::query()->where('enrollment_id', $enrollment->id)->count());
        $this->post('/manage/followup/'.$enrollment->id, $this->data('request', 'frozen'))->assertSessionHasErrors('action');
        self::assertSame(1, ReactivationRequest::query()->where('enrollment_id', $enrollment->id)->count());
        $this->post('/manage/followup/'.$enrollment->id, $this->data('resume', 'reactivation_requested'))->assertUnprocessable();
        self::assertSame(EnrollmentStatus::ReactivationRequested, $enrollment->fresh()->status);
    }

    public function test_approval_requires_a_passed_assessment_for_this_student_request_and_organization(): void
    {
        [$org,$actor,$enrollment,$student] = $this->context(EnrollmentStatus::UnderAssessment);
        $request = ReactivationRequest::factory()->create(['organization_id' => $org->id, 'enrollment_id' => $enrollment->id, 'requested_by' => $actor->id]);
        [$foreignOrg] = $this->context();
        $foreign = $this->assessment($foreignOrg, $actor, $student, $request, 100, true);
        $failed = $this->assessment($org, $actor, $student, $request, 30, false);
        $low = $this->assessment($org, $actor, $student, $request, 55, true);
        $this->actingAs($actor);
        foreach ([(string) Str::ulid(), (string) $foreign->id, (string) $failed->id, (string) $low->id] as $id) {
            $this->post('/manage/followup/'.$enrollment->id, [...$this->data('approve', 'under_assessment'), 'assessment_id' => $id])->assertSessionHasErrors('assessment_id');
            self::assertSame(ReactivationStatus::Pending, $request->fresh()->status);
            self::assertSame(EnrollmentStatus::UnderAssessment, $enrollment->fresh()->status);
        }
        $passed = $this->assessment($org, $actor, $student, $request, 80, true);
        $this->post('/manage/followup/'.$enrollment->id, [...$this->data('approve', 'under_assessment'), 'assessment_id' => $passed->id])->assertSessionHasNoErrors();
        self::assertSame(ReactivationStatus::Approved, $request->fresh()->status);
        self::assertSame((string) $passed->id, $request->fresh()->assessment_attempt_id);
        self::assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        self::assertNull($enrollment->fresh()->frozen_at);
        $this->post('/manage/followup/'.$enrollment->id, [...$this->data('approve', 'under_assessment'), 'assessment_id' => $passed->id])->assertSessionHasErrors('action');
        self::assertSame(1, DB::table('audit_log')->where('action', 'console.followup.approved')->count());
        $audit = DB::table('audit_log')->where('action', 'console.followup.approved')->first();
        self::assertSame('pending', json_decode($audit->old_values, true)['status']);
        self::assertSame('approved', json_decode($audit->new_values, true)['status']);

    }

    public function test_an_invalid_enrollment_transition_rolls_back_the_reactivation_decision(): void
    {
        [$org,$actor,$enrollment,$student] = $this->context(EnrollmentStatus::ReactivationRequested);
        $request = ReactivationRequest::factory()->create(['organization_id' => $org->id, 'enrollment_id' => $enrollment->id, 'requested_by' => $actor->id]);
        $assessment = $this->assessment($org, $actor, $student, $request, 90, true);
        $this->actingAs($actor)->post('/manage/followup/'.$enrollment->id, [...$this->data('approve', 'reactivation_requested'), 'assessment_id' => $assessment->id])->assertSessionHasErrors('action');
        self::assertSame(ReactivationStatus::Pending, $request->fresh()->status);
        self::assertNull($request->fresh()->reviewed_at);
        self::assertSame(EnrollmentStatus::ReactivationRequested, $enrollment->fresh()->status);
        self::assertSame(0, DB::table('audit_log')->where('action', 'console.followup.approved')->count());
        $this->post('/manage/followup/'.$enrollment->id, $this->data('assess', 'reactivation_requested'))->assertSessionHasNoErrors();
        self::assertSame(EnrollmentStatus::UnderAssessment, $enrollment->fresh()->status);
    }

    public function test_rejection_keeps_discipline_frozen_and_the_account_preserved(): void
    {
        [$org,$actor,$enrollment,$student] = $this->context(EnrollmentStatus::UnderAssessment);
        $request = ReactivationRequest::factory()->create(['organization_id' => $org->id, 'enrollment_id' => $enrollment->id, 'requested_by' => $actor->id]);
        $this->actingAs($actor)->post('/manage/followup/'.$enrollment->id, $this->data('reject', 'under_assessment'))->assertSessionHasNoErrors();
        self::assertSame(ReactivationStatus::Rejected, $request->fresh()->status);
        self::assertSame(EnrollmentStatus::Frozen, $enrollment->fresh()->status);
        self::assertNotNull(User::query()->find($student->user_id));
    }

    public function test_request_submission_permission_does_not_allow_approval_and_legacy_open_requests_can_be_reviewed(): void
    {
        [$org,$actor,$enrollment] = $this->context(EnrollmentStatus::Frozen);
        $this->permissions = ['admin.panel.access', 'student.view.any', 'enrollment.view', 'attendance.view', 'discipline.view_any', 'enrollment.pause', 'discipline.request_reactivation'];
        $this->actingAs($actor)->post('/manage/followup/'.$enrollment->id, $this->data('request', 'frozen'))->assertSessionHasNoErrors();
        $this->post('/manage/followup/'.$enrollment->id, $this->data('assess', 'reactivation_requested'))->assertForbidden();
        self::assertSame(EnrollmentStatus::ReactivationRequested, $enrollment->fresh()->status);
        $this->permissions[] = 'enrollment.reactivate';
        [$legacyOrg,$legacyActor,$legacyEnrollment] = $this->context(EnrollmentStatus::Frozen);
        $legacy = ReactivationRequest::factory()->create(['organization_id' => $legacyOrg->id, 'enrollment_id' => $legacyEnrollment->id, 'requested_by' => $legacyActor->id]);
        $this->actingAs($legacyActor)->post('/manage/followup/'.$legacyEnrollment->id, $this->data('assess', 'frozen'))->assertSessionHasNoErrors();
        self::assertSame(EnrollmentStatus::UnderAssessment, $legacyEnrollment->fresh()->status);
        self::assertSame(ReactivationStatus::Pending, $legacy->fresh()->status);
        self::assertSame(1, ReactivationRequest::query()->where('enrollment_id', $legacyEnrollment->id)->count());
    }

    public function test_accepting_an_excuse_requires_waiver_permission_and_changes_attendance_and_violation_atomically(): void
    {
        [$org,$actor,$enrollment,$student,$course] = $this->context();
        $attendance = $this->absence($org, $actor, $enrollment, $student, $course, now()->subDay()->toISOString());
        $participant = SessionParticipant::query()->findOrFail($attendance->session_participant_id);
        $violation = ViolationEvent::query()->create(['organization_id' => $org->id, 'enrollment_id' => $enrollment->id, 'student_profile_id' => $student->id, 'session_id' => $participant->session_id, 'type' => ViolationType::UnexcusedAbsence, 'occurred_at' => now()->subDay(), 'window_key' => 'R30', 'is_countable' => true]);
        $url = '/manage/followup/'.$enrollment->id.'/attendance/'.$attendance->id;
        $this->permissions = array_values(array_diff($this->permissions, ['discipline.waive_violations']));
        $this->actingAs($actor)->post($url, ['status' => 'excused', 'expected_status' => 'absent', 'context' => 'accepted_excuse'])->assertForbidden();
        self::assertSame(AttendanceStatus::Absent, $attendance->fresh()->status);
        self::assertNull($violation->fresh()->waived_at);
        $this->permissions[] = 'discipline.waive_violations';
        $this->post($url, ['status' => 'excused', 'expected_status' => 'absent', 'context' => 'accepted_excuse'])->assertSessionHasNoErrors();
        self::assertSame(AttendanceStatus::Excused, $attendance->fresh()->status);
        self::assertNotNull($violation->fresh()->waived_at);
        self::assertSame((string) $actor->id, $violation->fresh()->waived_by);
        self::assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        self::assertSame(1, DB::table('audit_log')->where('action', 'attendance.overridden')->count());
        self::assertSame(1, DB::table('audit_log')->where('action', 'console.followup.violation_waived')->count());
        $this->post($url, ['status' => 'present', 'expected_status' => 'absent', 'context' => 'recording_correction'])->assertSessionHasErrors('status');
        self::assertSame(AttendanceStatus::Excused, $attendance->fresh()->status);
    }

    public function test_attendance_correction_cannot_target_a_foreign_enrollment_or_create_a_new_violation(): void
    {
        [$org,$actor,$enrollment,$student,$course] = $this->context();
        $attendance = $this->absence($org, $actor, $enrollment, $student, $course, now()->subDay()->toISOString());
        [,$foreignActor,$foreignEnrollment] = $this->context();
        $this->actingAs($foreignActor)->post('/manage/followup/'.$foreignEnrollment->id.'/attendance/'.$attendance->id, ['status' => 'excused', 'expected_status' => 'absent', 'context' => 'accepted_excuse'])->assertNotFound();
        $this->actingAs($actor)->post('/manage/followup/'.$enrollment->id.'/attendance/'.$attendance->id, ['status' => 'no_show', 'expected_status' => 'absent', 'context' => 'recording_correction'])->assertSessionHasErrors('status');
        self::assertSame(AttendanceStatus::Absent, $attendance->fresh()->status);
        self::assertSame(0, DB::table('audit_log')->where('action', 'attendance.overridden')->count());
    }

    /** @return array<string,string> */
    private function data(string $action, string $status): array
    {
        return ['action' => $action, 'expected_status' => $status, 'context' => 'student_request'];
    }

    /** @return array{Organization,User,Enrollment,StudentProfile,Course} */
    private function context(EnrollmentStatus $status = EnrollmentStatus::Active): array
    {
        $org = Organization::factory()->create(['default_timezone' => 'Africa/Cairo']);
        $actor = User::factory()->inOrganization((string) $org->id)->create(['timezone' => 'Africa/Cairo']);
        $user = User::factory()->inOrganization((string) $org->id)->create();
        $student = StudentProfile::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id]);
        $program = Program::factory()->create(['organization_id' => $org->id]);
        $level = Level::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['organization_id' => $org->id, 'level_id' => $level->id]);
        $enrollment = Enrollment::query()->create(['organization_id' => $org->id, 'student_profile_id' => $student->id, 'program_id' => $program->id, 'status' => $status, 'applied_at' => now()->subMonth(), 'frozen_at' => $status === EnrollmentStatus::Active ? null : now()->subDay(), 'frozen_reason' => $status === EnrollmentStatus::Active ? null : 'Existing discipline decision']);

        return [$org, $actor, $enrollment, $student, $course];
    }

    private function absence(Organization $org, User $actor, Enrollment $enrollment, StudentProfile $student, Course $course, string $at): Attendance
    {
        $teacher = StaffProfile::query()->firstOrCreate(['organization_id' => $org->id, 'user_id' => $actor->id], ['staff_code' => 'T'.$actor->id, 'employment_type' => 'contractor']);
        $session = Session::factory()->create(['organization_id' => $org->id, 'course_id' => $course->id, 'staff_profile_id' => $teacher->id, 'scheduled_start' => CarbonImmutable::parse($at), 'scheduled_end' => CarbonImmutable::parse($at)->addHour()]);
        $participant = SessionParticipant::query()->create(['session_id' => $session->id, 'enrollment_id' => $enrollment->id, 'student_profile_id' => $student->id, 'invited_at' => now()->subMonth(), 'join_url_token' => Str::random(64)]);

        return Attendance::factory()->create(['session_participant_id' => $participant->id, 'status' => AttendanceStatus::Absent, 'derived_status' => AttendanceStatus::Absent, 'confirmed_by' => $actor->id, 'confirmed_at' => now()->subDay()]);
    }

    private function assessment(Organization $org, User $actor, StudentProfile $student, ReactivationRequest $request, int $score, bool $passed): AssessmentAttempt
    {
        $assessment = Assessment::factory()->create(['organization_id' => $org->id, 'created_by' => $actor->id, 'type' => AssessmentType::Reactivation, 'total_score' => 100, 'passing_score' => 50]);

        return AssessmentAttempt::factory()->graded($score, $passed)->create(['assessment_id' => $assessment->id, 'student_profile_id' => $student->id, 'reactivation_request_id' => $request->id, 'graded_by' => $actor->id]);
    }
}

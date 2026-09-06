<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Assignments\Application\Actions\CreateAssignmentAction;
use Modules\Assignments\Domain\Contracts\StudentAssignmentResultQueries;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Identity\Domain\Models\User;
use Modules\Staff\Domain\Models\StaffProfile;
use Tests\TestCase;

final class ProfileAssignmentResultsTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    /** @var array<string,list<string>> */
    private array $grants = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 9, 6)->setTime(12, 0));
        config(['console.enabled' => true]);
        foreach (['student.view', 'session.view', 'assignment.submit', 'assignment.manage', 'assignment.grade', 'session_report.view'] as $permission) {
            Gate::define($permission, fn (User $actor): bool => in_array($permission, $this->grants[(string) $actor->id] ?? [], true));
        }
    }

    public function test_real_submission_and_grading_appear_on_both_profiles_without_classmate_or_other_teacher_leaks(): void
    {
        [$student, $teacher, $session, $participant, $assignment] = $this->fixture();
        $this->actingAs($student, 'web')->post('/learn/student/assignments/'.$assignment->id.'/submit', [
            'content' => 'إجابة الطالب على الواجب', 'attachments' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->id)
            ->where('student_profile_id', $participant['student_profile_id'])->sole();
        $this->assertSame(AssignmentSubmissionStatus::Submitted, $submission->status);
        $this->actingAs($teacher, 'web')->postJson('/api/assignment-submissions/'.$submission->id.'/grade', [
            'score' => 18, 'feedback' => 'أحسنت، راجع موضع المد فقط.', 'reason' => 'تصحيح الواجب',
        ])->assertOk();
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'score' => 18, 'status' => 'graded']);

        $foreignParticipant = DB::table('session_participants')->find($this->createSessionParticipant());
        AssignmentSubmission::query()->create([
            'assignment_id' => $assignment->id, 'student_profile_id' => $foreignParticipant->student_profile_id,
            'status' => AssignmentSubmissionStatus::Graded, 'is_late' => false, 'submitted_at' => now(), 'graded_at' => now(),
            'content' => 'PRIVATE CLASSMATE ANSWER', 'feedback' => 'PRIVATE CLASSMATE FEEDBACK', 'score' => 1,
        ]);
        $otherUser = User::factory()->inOrganization($session['organization_id'])->create();
        $otherTeacher = StaffProfile::query()->create(['organization_id' => $session['organization_id'], 'user_id' => $otherUser->id,
            'staff_code' => 'STF-'.Str::random(8), 'employment_type' => 'full_time']);
        $otherAssignment = $assignment->replicate();
        $otherAssignment->fill(['staff_profile_id' => $otherTeacher->id, 'title' => ['ar' => 'تكليف معلم آخر']])->save();
        AssignmentSubmission::query()->create([
            'assignment_id' => $otherAssignment->id, 'student_profile_id' => $participant['student_profile_id'],
            'status' => AssignmentSubmissionStatus::Graded, 'is_late' => false, 'submitted_at' => now(), 'graded_at' => now(),
            'content' => 'OTHER TEACHER ANSWER', 'feedback' => 'OTHER TEACHER FEEDBACK', 'score' => 7,
        ]);
        $future = $assignment->replicate();
        $future->fill(['assigned_at' => now()->addDay(), 'due_at' => now()->addDays(2), 'title' => ['ar' => 'PRIVATE FUTURE ASSIGNMENT']])->save();

        $teacherResponse = $this->actingAs($teacher, 'web')->get('/learn/teacher/students/'.$participant['student_profile_id']);
        $teacherResponse->assertOk()->assertInertia(fn (Assert $page) => $page->has('profileWorkspace.assignments', 1)
            ->where('profileWorkspace.assignments.0.id', $assignment->id)
            ->where('profileWorkspace.assignments.0.score', 18)->where('profileWorkspace.assignments.0.maxScore', 20)
            ->where('profileWorkspace.assignments.0.feedback', 'أحسنت، راجع موضع المد فقط.')
            ->where('profileWorkspace.assignments.0.submissionContent', 'إجابة الطالب على الواجب')
            ->where('profileWorkspace.assignments.0.submissionStatus', 'graded')
            ->where('profileWorkspace.assignments.0.submissionStatusLabel', 'تم التصحيح')
            ->where('profileWorkspace.assignments.0.submittedAt', fn ($date): bool => is_string($date))
            ->where('profileWorkspace.assignments.0.gradedAt', fn ($date): bool => is_string($date))
            ->missing('profileWorkspace.assignments.0.attachments')->missing('hub.contracts')->missing('hub.guardians'));
        foreach (['PRIVATE CLASSMATE', 'OTHER TEACHER', 'PRIVATE FUTURE'] as $private) {
            $this->assertStringNotContainsString($private, $teacherResponse->getContent());
        }
        $selfResponse = $this->actingAs($student, 'web')->get('/learn/student/profile');
        $selfResponse->assertOk()->assertInertia(fn (Assert $page) => $page->has('profileWorkspace.assignments', 2)
            ->where('profileWorkspace.assignments.0.score', 18)->where('profileWorkspace.assignments.0.maxScore', 20));
        $this->assertStringNotContainsString('PRIVATE CLASSMATE', $selfResponse->getContent());
        $this->assertStringNotContainsString('PRIVATE FUTURE', $selfResponse->getContent());
        $this->assertSame([], app(StudentAssignmentResultQueries::class)->forStudent($this->organizationId, (string) $student->id, $session['staff_profile_id']));
    }

    public function test_assignment_permission_and_current_study_are_rechecked_and_teacher_relationship_cannot_be_bypassed(): void
    {
        [$student, $teacher, $session, $participant] = $this->fixture();
        $this->actingAs($teacher, 'web')->get('/learn/teacher/students/'.$participant['student_profile_id'])
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('profileWorkspace.assignments', 1));
        $this->grants[(string) $teacher->id] = ['student.view', 'session.view'];
        $this->get('/learn/teacher/students/'.$participant['student_profile_id'])
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('profileWorkspace.assignments', 0));
        $this->grants[(string) $teacher->id][] = 'assignment.grade';
        DB::table('enrollments')->where('id', $participant['enrollment_id'])->update(['status' => 'paused']);
        $this->assertSame([], app(StudentAssignmentResultQueries::class)->forStudent($session['organization_id'], (string) $student->id, $session['staff_profile_id']));
        $this->actingAs($student, 'web')->get('/learn/student/profile')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('profileWorkspace.assignments', 0));
        DB::table('schedules')->where('staff_profile_id', $session['staff_profile_id'])->update(['is_active' => false]);
        $this->actingAs($teacher, 'web')->get('/learn/teacher/students/'.$participant['student_profile_id'])->assertNotFound();
    }

    /** @return array{User,User,array<string,mixed>,array<string,mixed>,Assignment} */
    private function fixture(): array
    {
        $participant = (array) DB::table('session_participants')->find($this->createSessionParticipant());
        $session = (array) DB::table('sessions')->find($participant['session_id']);
        $student = User::query()->findOrFail(DB::table('student_profiles')->where('id', $participant['student_profile_id'])->value('user_id'));
        $teacher = User::query()->findOrFail(DB::table('staff_profiles')->where('id', $session['staff_profile_id'])->value('user_id'));
        $this->grants[(string) $student->id] = ['session.view', 'assignment.submit'];
        $this->grants[(string) $teacher->id] = ['student.view', 'session.view', 'assignment.grade'];
        DB::table('teacher_courses')->insert(['id' => (string) Str::ulid(), 'staff_profile_id' => $session['staff_profile_id'],
            'course_id' => $session['course_id'], 'qualified_by' => $teacher->id]);
        DB::table('schedules')->insert([
            'id' => (string) Str::ulid(), 'organization_id' => $session['organization_id'], 'course_id' => $session['course_id'],
            'staff_profile_id' => $session['staff_profile_id'], 'student_profile_id' => $participant['student_profile_id'],
            'session_type' => 'regular', 'rrule' => 'FREQ=WEEKLY;BYDAY=MO', 'start_time' => '16:00',
            'duration_minutes' => 30, 'timezone' => 'UTC', 'starts_on' => '2026-09-01',
            'is_active' => true, 'materialized_until' => '2026-09-06', 'created_by' => $teacher->id,
        ]);
        $assignment = app(CreateAssignmentAction::class)->execute([
            'organization_id' => $session['organization_id'], 'course_id' => $session['course_id'],
            'staff_profile_id' => $session['staff_profile_id'], 'title' => ['ar' => 'تطبيق التجويد'],
            'assigned_at' => now()->subHour()->toIso8601String(), 'due_at' => now()->addDay()->toIso8601String(), 'max_score' => 20,
        ], (string) $teacher->id, 'تكليف تعليمي لاختبار الربط');

        return [$student, $teacher, $session, $participant, $assignment];
    }
}

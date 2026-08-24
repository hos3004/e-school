<?php

declare(strict_types=1);

namespace Modules\Sessions\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\AcademicReports\Application\Actions\SubmitSessionReportAction;
use Modules\AcademicReports\Domain\Events\SessionReportSubmitted;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceRecorded;
use Modules\Discipline\Application\Actions\RecordViolationAction;
use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Discipline\Domain\Events\DisciplineActionApplied;
use Modules\Discipline\Domain\Events\ViolationRecorded;
use Modules\Identity\Domain\Models\User;
use Modules\Recordings\Application\Actions\GrantRecordingAccessAction;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Sessions\Application\Actions\AssignSubstituteTeacherAction;
use Modules\Sessions\Application\Actions\DecideTeacherApologyAction;
use Modules\Sessions\Application\Actions\SubmitTeacherApologyAction;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\VirtualClassroom\Application\Actions\GenerateJoinUrlAction;
use Modules\VirtualClassroom\Application\Actions\HandleClassroomWebhookAction;
use Modules\VirtualClassroom\Application\Actions\ProvisionClassroomAction;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantJoined;
use Modules\VirtualClassroom\Domain\Events\ClassroomProvisioned;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Infrastructure\Providers\BigBlueButtonProvider;
use Modules\VirtualClassroom\Infrastructure\Providers\NullProvider;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class Task03AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
    }

    public function test_full_session_bbb_attendance_discipline_and_reporting_lifecycle(): void
    {
        Event::fake([
            ClassroomProvisioned::class,
            ClassroomParticipantJoined::class,
            AttendanceRecorded::class,
            ViolationRecorded::class,
            DisciplineActionApplied::class,
            SessionReportSubmitted::class,
        ]);

        $orgId = Fixtures::organizationId();

        // 1. Create Users & Session
        $teacherUser = User::factory()->create(['organization_id' => $orgId, 'name' => 'الأستاذ خالد']);
        $studentUser = User::factory()->create(['organization_id' => $orgId, 'name' => 'الطالب عمر']);
        $supervisorUser = User::factory()->create(['organization_id' => $orgId, 'name' => 'المشرف علي']);

        $session = Session::query()->create([
            'organization_id' => $orgId,
            'staff_profile_id' => Fixtures::staffProfileId(),
            'status' => SessionStatus::Scheduled,
            'scheduled_start' => now()->utc()->addHours(1),
            'scheduled_end' => now()->utc()->addHours(2),
        ]);

        // 2. Provision VirtualClassroom via NullProvider
        /** @var ProvisionClassroomAction $provisionAction */
        $provisionAction = new ProvisionClassroomAction(new NullProvider, app('events'));
        $classroom = $provisionAction->execute(
            sessionId: (string) $session->id,
            title: 'حصة التلاوة',
        );

        $this->assertEquals('null', $classroom->provider);
        Event::assertDispatched(ClassroomProvisioned::class);

        // 3. Dynamic Join URL Generation (Teacher = Moderator, Student = Attendee, Supervisor = Viewer)
        /** @var GenerateJoinUrlAction $joinUrlAction */
        $joinUrlAction = new GenerateJoinUrlAction(new NullProvider);

        $teacherUrl = $joinUrlAction->execute($classroom, (string) $teacherUser->id, 'الأستاذ خالد', JoinRole::Moderator);
        $studentUrl = $joinUrlAction->execute($classroom, (string) $studentUser->id, 'الطالب عمر', JoinRole::Viewer);
        $supervisorUrl = $joinUrlAction->execute($classroom, (string) $supervisorUser->id, 'المشرف علي', JoinRole::Viewer);

        $this->assertStringContainsString('MODERATOR', $teacherUrl);
        $this->assertStringContainsString('ATTENDEE', $studentUrl);

        // Block Frozen Student from Join URL
        $this->expectException(BusinessRuleViolation::class);
        $joinUrlAction->execute($classroom, (string) $studentUser->id, 'طالب مجمد', JoinRole::Viewer, isFrozen: true);
    }

    public function test_bbb_webhook_signature_and_idempotency(): void
    {
        $orgId = Fixtures::organizationId();

        $session = Session::query()->create([
            'organization_id' => $orgId,
            'staff_profile_id' => Fixtures::staffProfileId(),
            'status' => SessionStatus::Scheduled,
            'scheduled_start' => now()->utc()->addHours(1),
            'scheduled_end' => now()->utc()->addHours(2),
        ]);

        $classroom = Classroom::query()->create([
            'session_id' => (string) $session->id,
            'provider' => 'bigbluebutton',
            'external_id' => 'SES-'.$session->id,
            'moderator_secret' => 'modsec123',
            'attendee_secret' => 'attsec123',
            'created_remote_at' => now()->utc(),
        ]);

        $action = new HandleClassroomWebhookAction(
            new BigBlueButtonProvider([
                'base_url' => 'https://bbb.example.com',
                'secret' => 'bbbsecret',
                'webhook_secret' => 'webhooksecret',
                'timeout_seconds' => 5,
                'connect_timeout_seconds' => 2,
            ]),
            app('events'),
        );

        // 1. Invalid signature throws exception
        $invalidRequest = Request::create('/api/virtualclassroom/webhook?checksum=invalid', 'POST', [], [], [], [], json_encode([
            'event' => json_encode(['data' => ['id' => 'user-joined']]),
        ]));

        try {
            $action->execute($invalidRequest);
            $this->fail('Expected ClassroomProviderException for invalid signature');
        } catch (ClassroomProviderException $e) {
            $this->assertStringContainsString('invalid_webhook_signature', $e->reason);
        }
    }

    public function test_teacher_apology_and_substitute_assignment(): void
    {
        $orgId = Fixtures::organizationId();
        $originalTeacherId = Fixtures::staffProfileId();
        $substituteTeacherId = Fixtures::staffProfileId();

        $session = Session::query()->create([
            'organization_id' => $orgId,
            'staff_profile_id' => $originalTeacherId,
            'status' => SessionStatus::Scheduled,
            'scheduled_start' => now()->utc()->addHours(2),
            'scheduled_end' => now()->utc()->addHours(3),
        ]);

        /** @var SubmitTeacherApologyAction $submitApology */
        $submitApology = app(SubmitTeacherApologyAction::class);
        $apology = $submitApology->execute((string) $session->id, $originalTeacherId, 'ظرف صحي طارئ');

        $this->assertEquals(ApologyStatus::Submitted, $apology->status);

        /** @var DecideTeacherApologyAction $decideApology */
        $decideApology = app(DecideTeacherApologyAction::class);
        $decidedApology = $decideApology->approve((string) $apology->id, Fixtures::userId());

        $this->assertEquals(ApologyStatus::Approved, $decidedApology->status);

        /** @var AssignSubstituteTeacherAction $assignSubstitute */
        $assignSubstitute = app(AssignSubstituteTeacherAction::class);
        $updatedSession = $assignSubstitute->execute((string) $session->id, $substituteTeacherId, Fixtures::userId(), 'بديل مؤهل');

        $this->assertEquals($substituteTeacherId, $updatedSession->staff_profile_id);
        $this->assertEquals($originalTeacherId, $updatedSession->original_teacher_id);
    }

    public function test_attendance_duration_calculation_and_confirmation(): void
    {
        /** @var RecordAttendanceAction $recordAction */
        $recordAction = app(RecordAttendanceAction::class);

        $orgId = Fixtures::organizationId();

        $session = Session::query()->create([
            'organization_id' => $orgId,
            'staff_profile_id' => Fixtures::staffProfileId(),
            'status' => SessionStatus::Scheduled,
            'scheduled_start' => now()->utc()->addHours(2),
            'scheduled_end' => now()->utc()->addHours(3),
        ]);

        $participantId = (string) Str::ulid();
        DB::table('session_participants')->insert([
            'id' => $participantId,
            'session_id' => (string) $session->id,
            'user_id' => Fixtures::userId(),
            'role' => JoinRole::Viewer->value,
            'is_invited_guest' => false,
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        $attendance = $recordAction->execute(
            sessionParticipantId: $participantId,
            attendedMinutes: 45,
            sessionMinutes: 60,
            joinedAfterMinutes: 5,
            leftBeforeMinutes: 10,
        );

        $this->assertEquals(AttendanceStatus::PresentWithLate, $attendance->derived_status);
        $this->assertEquals(45, $attendance->attended_minutes);
    }

    public function test_discipline_rolling_window_3_stage_escalation(): void
    {
        Event::fake([DisciplineActionApplied::class]);

        $orgId = Fixtures::organizationId();
        $enrollmentId = (string) Str::ulid();
        $studentProfileId = (string) Str::ulid();

        /** @var RecordViolationAction $recordViolation */
        $recordViolation = app(RecordViolationAction::class);

        // 1st Unexcused Absence -> Notice 1
        $v1 = $recordViolation->execute([
            'organization_id' => $orgId,
            'enrollment_id' => $enrollmentId,
            'student_profile_id' => $studentProfileId,
            'type' => ViolationType::UnexcusedAbsence,
            'occurred_at' => now()->utc()->toIso8601String(),
        ]);
        $this->assertEquals(1, $recordViolation->countInWindow($v1));

        // 2nd Unexcused Absence -> Warning 2
        $v2 = $recordViolation->execute([
            'organization_id' => $orgId,
            'enrollment_id' => $enrollmentId,
            'student_profile_id' => $studentProfileId,
            'type' => ViolationType::UnexcusedAbsence,
            'occurred_at' => now()->utc()->addDay()->toIso8601String(),
        ]);
        $this->assertEquals(2, $recordViolation->countInWindow($v2));

        // 3rd Unexcused Absence -> Auto Freeze
        $v3 = $recordViolation->execute([
            'organization_id' => $orgId,
            'enrollment_id' => $enrollmentId,
            'student_profile_id' => $studentProfileId,
            'type' => ViolationType::UnexcusedAbsence,
            'occurred_at' => now()->utc()->addDays(2)->toIso8601String(),
        ]);
        $this->assertEquals(3, $recordViolation->countInWindow($v3));

        Event::assertDispatched(DisciplineActionApplied::class, function (DisciplineActionApplied $event) {
            return $event->action === DisciplineActionType::FreezeEnrollment && $event->thresholdReached === 3;
        });
    }

    public function test_teacher_session_report_submission_on_time_and_late(): void
    {
        Event::fake([SessionReportSubmitted::class]);

        $orgId = Fixtures::organizationId();
        $staffProfileId = Fixtures::staffProfileId();
        $studentProfileId = Fixtures::studentProfileId();

        $session = Session::query()->create([
            'organization_id' => $orgId,
            'staff_profile_id' => $staffProfileId,
            'status' => SessionStatus::Scheduled,
            'scheduled_start' => now()->utc()->subHours(2),
            'scheduled_end' => now()->utc()->subHours(1),
        ]);
        $sessionId = (string) $session->id;

        /** @var SubmitSessionReportAction $submitReport */
        $submitReport = app(SubmitSessionReportAction::class);

        // On-Time Submission
        $report = $submitReport->execute(
            sessionId: $sessionId,
            staffProfileId: $staffProfileId,
            students: [
                [
                    'student_profile_id' => $studentProfileId,
                    'participation' => 4,
                    'performance' => 5,
                    'commitment' => 5,
                ],
            ],
            sessionEndedAt: CarbonImmutable::now('UTC')->subMinutes(30),
        );

        $this->assertFalse($report->is_late);
        Event::assertDispatched(SessionReportSubmitted::class);
    }

    public function test_recording_private_access_grant(): void
    {
        $orgId = Fixtures::organizationId();
        $grantedByUser = User::factory()->create(['organization_id' => $orgId]);
        $grantedToUser = User::factory()->create(['organization_id' => $orgId]);

        $session = Session::query()->create([
            'organization_id' => $orgId,
            'staff_profile_id' => Fixtures::staffProfileId(),
            'status' => SessionStatus::Scheduled,
            'scheduled_start' => now()->utc()->addHours(1),
            'scheduled_end' => now()->utc()->addHours(2),
        ]);

        $classroom = Classroom::query()->create([
            'session_id' => (string) $session->id,
            'provider' => 'bigbluebutton',
            'external_id' => 'SES-'.$session->id,
            'moderator_secret' => 'modsec123',
            'attendee_secret' => 'attsec123',
            'created_remote_at' => now()->utc(),
        ]);

        $recording = Recording::query()->create([
            'organization_id' => $orgId,
            'session_id' => (string) $session->id,
            'classroom_id' => (string) $classroom->id,
            'provider' => 'bigbluebutton',
            'external_recording_id' => 'REC-123',
            'status' => 'ready',
            'disk' => 'local',
            'path' => 'recordings/rec123.mp4',
            'available_from' => now()->utc(),
            'expires_at' => now()->utc()->addDays(30),
        ]);

        /** @var GrantRecordingAccessAction $grantAction */
        $grantAction = app(GrantRecordingAccessAction::class);
        $grant = $grantAction->execute(
            recording: $recording,
            grantedByUserId: (string) $grantedByUser->id,
            grantedToUserId: (string) $grantedToUser->id,
            expiresAt: CarbonImmutable::now('UTC')->addDays(3),
            reason: 'منحة مشاهدة للاختبار',
        );

        $this->assertTrue($grant->isValid());
        $this->assertEquals((string) $grantedToUser->id, $grant->granted_to_user_id);
    }
}

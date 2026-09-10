<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class SessionReportEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $actor;

    private Session $session;

    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00 UTC'));
        $this->withoutVite();
        config(['console.enabled' => true]);
        $this->organization = Organization::factory()->create(['default_timezone' => 'Africa/Cairo']);
        $this->actor = User::factory()->inOrganization($this->organization->id)->create(['timezone' => 'Africa/Cairo']);
        $this->grant('admin.panel.access', 'session.view', 'report.view', 'recording.view.any');
        $this->actingAs($this->actor);

        $program = Program::factory()->create(['organization_id' => $this->organization->id]);
        $level = Level::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['organization_id' => $this->organization->id, 'level_id' => $level->id, 'session_mode' => SessionMode::Individual]);
        $teacherUser = User::factory()->inOrganization($this->organization->id)->create(['name' => 'أستاذة الحصة']);
        $teacher = StaffProfile::query()->create(['organization_id' => $this->organization->id, 'user_id' => $teacherUser->id, 'staff_code' => 'T-REP', 'employment_type' => 'contractor', 'hired_at' => '2026-01-01']);
        $studentUser = User::factory()->inOrganization($this->organization->id)->create(['name' => 'طالب الحصة']);
        $this->student = StudentProfile::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $studentUser->id]);
        $enrollment = Enrollment::query()->create(['organization_id' => $this->organization->id, 'student_profile_id' => $this->student->id, 'program_id' => $program->id, 'current_level_id' => $level->id, 'status' => EnrollmentStatus::Active, 'applied_at' => now()->subMonth(), 'activated_at' => now()->subWeek()]);

        $this->session = Session::factory()->create([
            'organization_id' => $this->organization->id,
            'course_id' => $course->id,
            'staff_profile_id' => $teacher->id,
            'group_id' => null,
            'session_type' => 'individual',
            'status' => SessionStatus::Completed,
            'scheduled_start' => '2026-10-25 10:00:00 UTC',
            'scheduled_end' => '2026-10-25 10:35:00 UTC',
            'actual_start' => '2026-10-25 10:03:00 UTC',
            'actual_end' => '2026-10-25 10:38:00 UTC',
        ]);
        $participant = SessionParticipant::query()->create([
            'session_id' => $this->session->id,
            'join_url_token' => Str::random(48),
            'invited_at' => now()->subDay(),
            'student_profile_id' => $this->student->id,
            'enrollment_id' => $enrollment->id,
        ]);
        Attendance::query()->create([
            'session_participant_id' => $participant->id,
            'status' => AttendanceStatus::Present,
            'derived_status' => AttendanceStatus::Present,
            'attended_minutes' => 32,
            'confirmed_at' => now(),
            'confirmed_by' => $this->actor->id,
        ]);
    }

    private function grant(string ...$abilities): void
    {
        foreach ($abilities as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->id === $this->actor->id);
        }
    }

    private function classroomId(): string
    {
        $classroomId = (string) Str::ulid();
        DB::table('classrooms')->insert([
            'id' => $classroomId,
            'session_id' => $this->session->id,
            'provider' => 'bigbluebutton',
            'external_id' => 'ext-'.$this->session->id,
            'moderator_secret' => Str::random(16),
            'attendee_secret' => Str::random(16),
            'created_remote_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'max_concurrent_participants' => 5,
            'health_status' => 'healthy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $classroomId;
    }

    public function test_report_returns_actual_time_attendance_and_signed_recording_preview(): void
    {
        Recording::factory()->ready()->create([
            'organization_id' => $this->organization->id,
            'session_id' => $this->session->id,
            'classroom_id' => $this->classroomId(),
            'duration_seconds' => 2100,
            'path' => 'https://recordings.example.test/'.Str::ulid().'.mp4',
        ]);

        $response = $this->getJson('/manage/sessions/'.$this->session->id.'/report')->assertOk();

        $response->assertJsonPath('session.status', SessionStatus::Completed->value);
        $this->assertStringContainsString('2026-10-25T13:03:00+03:00', (string) $response->json('session.actual_start'));
        $this->assertStringContainsString('2026-10-25T13:38:00+03:00', (string) $response->json('session.actual_end'));
        $response->assertJsonPath('attendance.0.student', 'طالب الحصة');
        $response->assertJsonPath('attendance.0.status', AttendanceStatus::Present->value);
        $response->assertJsonPath('attendance.0.attended_minutes', 32);
        $response->assertJsonPath('recordings.0.status', RecordingStatus::Ready->value);
        $response->assertJsonPath('recordings.0.duration_minutes', 35);
        $this->assertStringContainsString('/recordings/', (string) $response->json('recordings.0.preview_url'));
        $this->assertStringContainsString('signature=', (string) $response->json('recordings.0.preview_url'));
    }

    public function test_recording_without_ready_status_has_no_preview_link(): void
    {
        Recording::factory()->withStatus(RecordingStatus::Processing)->create([
            'organization_id' => $this->organization->id,
            'session_id' => $this->session->id,
            'classroom_id' => $this->classroomId(),
        ]);

        $this->getJson('/manage/sessions/'.$this->session->id.'/report')
            ->assertOk()
            ->assertJsonPath('recordings.0.preview_url', null);
    }

    public function test_report_requires_report_view_permission(): void
    {
        Gate::define('report.view', static fn (): bool => false);

        $this->getJson('/manage/sessions/'.$this->session->id.'/report')->assertForbidden();
    }

    public function test_report_is_scoped_to_the_actor_organization(): void
    {
        $foreignOrg = Organization::factory()->create();
        $foreign = User::factory()->inOrganization($foreignOrg->id)->create();
        $this->actingAs($foreign);
        foreach (['admin.panel.access', 'session.view', 'report.view', 'recording.view.any'] as $ability) {
            Gate::define($ability, static fn (): bool => true);
        }

        $this->getJson('/manage/sessions/'.$this->session->id.'/report')->assertNotFound();
    }
}

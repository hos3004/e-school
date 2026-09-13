<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
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
 * تغيير معلم الكورس الفردي من صفحة الطالب.
 *
 * المحروس: الحصص القادمة تنتقل للمعلم الجديد فعلًا، والماضية لا تُمس، والتغيير
 * ليس «معلمًا بديلًا» فلا يُنشئ سجل استبدال ولا يظهر كتغطية بديل.
 */
final class ConsoleIndividualTeacherChangeTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'student.view.any', 'schedule.manage', 'schedule.view',
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

    public function test_changing_the_teacher_moves_future_sessions_and_keeps_the_past(): void
    {
        $admin = User::factory()->inOrganization($this->organizationId)->create();
        $student = $this->student();
        $current = $this->teacher();
        $replacement = $this->teacher();
        $schedule = $this->schedule($student, $current);
        $past = $this->sessionRow($schedule, $current, CarbonImmutable::now('UTC')->subWeek(), SessionStatus::Completed);
        $future = $this->sessionRow($schedule, $current, CarbonImmutable::now('UTC')->addWeek(), SessionStatus::Scheduled);

        $this->actingAs($admin, 'web')
            ->put('/manage/students/'.$student->id.'/teacher', [
                'schedule_id' => (string) $schedule->getKey(), 'staff_profile_id' => $replacement,
            ])->assertSessionHasErrors('reason');

        $this->put('/manage/students/'.$student->id.'/teacher', [
            'schedule_id' => (string) $schedule->getKey(),
            'staff_profile_id' => $replacement,
            'reason' => 'نقل الطالب لمعلم آخر بطلب ولي الأمر',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($replacement, (string) $schedule->refresh()->staff_profile_id);
        $this->assertSame($current, (string) $past->refresh()->staff_profile_id);
        $this->assertSame(SessionStatus::Completed, $past->status);
        $this->assertNotSame(
            SessionStatus::Scheduled,
            $future->refresh()->status,
            'الحصة القادمة للمعلم السابق يجب أن تُلغى بدل أن تبقى في جدوله',
        );
        $this->assertDatabaseCount('session_substitutions', 0);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'scheduling.schedule_updated',
            'auditable_id' => (string) $schedule->getKey(),
        ]);
    }

    public function test_the_same_teacher_and_unqualified_teachers_are_rejected(): void
    {
        $admin = User::factory()->inOrganization($this->organizationId)->create();
        $student = $this->student();
        $current = $this->teacher();
        $unqualified = Fixtures::staffProfileId();
        $schedule = $this->schedule($student, $current);

        $this->actingAs($admin, 'web')->put('/manage/students/'.$student->id.'/teacher', [
            'schedule_id' => (string) $schedule->getKey(),
            'staff_profile_id' => $current,
            'reason' => 'نفس المعلم',
        ])->assertSessionHas('error');

        $this->put('/manage/students/'.$student->id.'/teacher', [
            'schedule_id' => (string) $schedule->getKey(),
            'staff_profile_id' => $unqualified,
            'reason' => 'معلم غير مؤهل',
        ])->assertSessionHas('error');

        $this->assertSame($current, (string) $schedule->refresh()->staff_profile_id);
    }

    public function test_the_section_and_route_are_closed_without_schedule_permission(): void
    {
        $admin = User::factory()->inOrganization($this->organizationId)->create();
        $student = $this->student();
        $current = $this->teacher();
        $replacement = $this->teacher();
        $schedule = $this->schedule($student, $current);

        $this->permissions = ['admin.panel.access', 'student.view.any'];
        $this->actingAs($admin, 'web')->get('/manage/students/'.$student->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('teacherChange', null));
        $this->put('/manage/students/'.$student->id.'/teacher', [
            'schedule_id' => (string) $schedule->getKey(),
            'staff_profile_id' => $replacement,
            'reason' => 'بلا صلاحية',
        ])->assertForbidden();
        $this->assertSame($current, (string) $schedule->refresh()->staff_profile_id);
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

    private function schedule(StudentProfile $student, string $staffProfileId): Schedule
    {
        return Schedule::query()->create([
            'organization_id' => $this->organizationId,
            'group_id' => null,
            'student_profile_id' => (string) $student->id,
            'course_id' => $this->courseId,
            'staff_profile_id' => $staffProfileId,
            'session_type' => 'individual',
            'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO',
            'start_time' => '10:00',
            'duration_minutes' => (int) (config('scheduling.individual_session_durations')[0] ?? 25),
            'timezone' => 'UTC',
            'starts_on' => CarbonImmutable::now('UTC')->subMonth()->toDateString(),
            'materialized_until' => CarbonImmutable::now('UTC')->addMonth(),
            'is_active' => true,
            'created_by' => Fixtures::userId(),
        ]);
    }

    private function sessionRow(
        Schedule $schedule,
        string $staffProfileId,
        CarbonImmutable $start,
        SessionStatus $status,
    ): Session {
        return Session::factory()->create([
            'organization_id' => $this->organizationId,
            'schedule_id' => (string) $schedule->getKey(),
            'group_id' => null,
            'course_id' => $this->courseId,
            'staff_profile_id' => $staffProfileId,
            'session_type' => 'individual',
            'scheduled_start' => $start,
            'scheduled_end' => $start->addMinutes((int) $schedule->duration_minutes),
            'status' => $status,
            'title' => ['ar' => 'حصة', 'en' => 'Session'],
            'id' => (string) Str::ulid(),
        ]);
    }
}

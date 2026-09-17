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
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

/**
 * سعر حصة المعلم في صفحة تسكين القرآن الفردي (Console/Quran).
 *
 * هذا مسار تسكين مختلف عن StudentTeacherController (ملف الطالب)؛ كان يقبل
 * مدة مخصّصة أصلًا لكن بلا أي حقل لتحديد سعر الحصة، فتُرفض أي مدة خارج
 * الكتالوج بلا سعر مسجَّل مسبقًا من مكان آخر. صار يمكن تحديد السعر هنا
 * مباشرة عند الحفظ، بنفس منطق StudentTeacherController::recordRate.
 */
final class ConsoleQuranRateAndDurationTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOM_DURATION = 40;

    private Organization $organization;

    private User $actor;

    private Program $program;

    private Course $course;

    private StaffProfile $teacher;

    private StudentProfile $student;

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
        $level = Level::factory()->create(['program_id' => $this->program->id]);
        $this->course = Course::factory()->create([
            'organization_id' => $this->organization->id, 'level_id' => $level->id,
            'code' => config('scheduling.individual_quran.course_code'), 'session_mode' => SessionMode::Individual, 'is_active' => true,
        ]);
        $teacherUser = User::factory()->inOrganization($this->organization->id)->create(['name' => 'معلم القرآن']);
        $this->teacher = StaffProfile::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $teacherUser->id, 'staff_code' => 'Q-RATE', 'employment_type' => 'contractor', 'hired_at' => '2026-01-01',
        ]);
        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(), 'staff_profile_id' => $this->teacher->id, 'course_id' => $this->course->id,
            'qualified_at' => now(), 'qualified_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        TeacherContract::query()->create([
            'organization_id' => $this->organization->id, 'staff_profile_id' => $this->teacher->id,
            'basis' => ContractBasis::PerSession, 'effective_from' => CarbonImmutable::now('UTC')->subMonths(2)->toDateString(), 'currency' => 'EGP',
        ]);
        foreach ([0, 3] as $weekday) {
            TeacherAvailability::query()->create([
                'staff_profile_id' => $this->teacher->id, 'weekday' => $weekday,
                'start_time' => '09:00', 'end_time' => '18:00', 'timezone' => 'UTC',
                'effective_from' => '2026-10-01', 'effective_to' => '2026-12-31',
                'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
            ]);
        }
        $studentUser = User::factory()->inOrganization($this->organization->id)->create(['name' => 'طالب القرآن']);
        $this->student = StudentProfile::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $studentUser->id]);
        Enrollment::query()->create([
            'organization_id' => $this->organization->id, 'student_profile_id' => $this->student->id,
            'program_id' => $this->program->id, 'current_level_id' => $level->id,
            'status' => EnrollmentStatus::Active, 'applied_at' => now()->subMonth(), 'activated_at' => now()->subWeek(),
        ]);
    }

    public function test_a_priced_duration_still_needs_no_teacher_rate(): void
    {
        $priced = (int) (config('scheduling.individual_session_durations')[0] ?? 25);

        $this->postJson('/manage/quran/'.$this->student->id, $this->payload($priced))
            ->assertOk()->assertJsonPath('schedule.duration_minutes', $priced);

        $this->assertDatabaseCount('teacher_rates', 0);
    }

    public function test_a_custom_duration_is_refused_while_the_teacher_has_no_rate(): void
    {
        $this->postJson('/manage/quran/'.$this->student->id, $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('placement');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_the_rate_reason_is_required_when_setting_a_rate(): void
    {
        $this->postJson('/manage/quran/'.$this->student->id, [
            ...$this->payload(), 'session_rate_major' => '48.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('rate_reason');

        $this->assertDatabaseCount('schedules', 0);
        $this->assertDatabaseCount('teacher_rates', 0);
    }

    public function test_a_custom_duration_is_accepted_once_a_rate_is_sent_with_the_placement(): void
    {
        $this->postJson('/manage/quran/'.$this->student->id, [
            ...$this->payload(),
            'session_rate_major' => '48.00',
            'rate_reason' => 'سعر حصة القرآن المتفق عليه مع المعلم',
        ])->assertOk()->assertJsonPath('schedule.duration_minutes', self::CUSTOM_DURATION);

        $schedule = Schedule::query()->where('student_profile_id', $this->student->id)->firstOrFail();
        $this->assertSame(self::CUSTOM_DURATION, (int) $schedule->duration_minutes);
        $this->assertDatabaseHas('teacher_rates', [
            'scope' => RateScope::Course->value,
            'course_id' => $this->course->id,
            'program_id' => $this->program->id,
            'amount' => 4800,
            'effective_to' => null,
        ]);
    }

    public function test_the_rate_endpoint_reports_the_course_rate_for_the_selected_teacher(): void
    {
        $this->getJson('/manage/quran/rate?staff_profile_id='.$this->teacher->id)
            ->assertOk()->assertJsonPath('rate_major', null)->assertJsonPath('requires_rate', true);

        $this->postJson('/manage/quran/'.$this->student->id, [
            ...$this->payload(),
            'session_rate_major' => '48.00',
            'rate_reason' => 'سعر حصة القرآن المتفق عليه مع المعلم',
        ])->assertOk();

        // السعر يسري من تاريخ بداية التسكين (2026-10-11)، فلا يظهر "الآن" إلا بعده.
        $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00:00 UTC'));
        $this->getJson('/manage/quran/rate?staff_profile_id='.$this->teacher->id)
            ->assertOk()->assertJsonPath('rate_major', '48.00');
    }

    public function test_resending_the_same_rate_records_nothing_new(): void
    {
        $data = [
            ...$this->payload(),
            'session_rate_major' => '48.00',
            'rate_reason' => 'سعر حصة القرآن المتفق عليه مع المعلم',
        ];
        $this->postJson('/manage/quran/'.$this->student->id, $data)->assertOk();
        $schedule = Schedule::query()->where('student_profile_id', $this->student->id)->firstOrFail();

        $this->patchJson('/manage/quran/'.$this->student->id.'/schedules/'.$schedule->id, $data)->assertOk();

        $this->assertSame(1, DB::table('teacher_rates')->count());
    }

    /** @return array<string, mixed> */
    private function payload(int $duration = self::CUSTOM_DURATION): array
    {
        return [
            'staff_profile_id' => $this->teacher->id,
            'weekly_slots' => [['weekday' => 0, 'start_time' => '09:00'], ['weekday' => 3, 'start_time' => '12:00']],
            'duration_minutes' => $duration, 'interval_weeks' => 1, 'timezone' => 'UTC',
            'starts_on' => '2026-10-11', 'ends_on' => '2026-10-14',
        ];
    }
}

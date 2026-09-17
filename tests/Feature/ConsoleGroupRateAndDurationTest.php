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
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Domain\Models\TeacherContract;
use Tests\TestCase;

/**
 * مدة حصة المجموعة المخصّصة وسعر حصة المعلم من محرر جدول المجموعة.
 *
 * المحروس: المدد المسعّرة في كتالوج المؤسسة تبقى مقبولة دون سعر، وأي مدة
 * أخرى ضمن حدود المؤسسة تُقبل للمجموعات أيضًا بشرط سعر ساري للمعلم عن هذا
 * الكورس — بنفس منطق الحصص الفردية، فلا تُقفل حصص المجموعة بلا قيدة مستحق.
 */
final class ConsoleGroupRateAndDurationTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOM_DURATION = 47;

    private Organization $organization;

    private User $actor;

    private Program $program;

    private Course $course;

    private StaffProfile $teacher;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 08:00 UTC'));
        $this->withoutVite();
        config(['console.enabled' => true]);
        $this->organization = Organization::factory()->create(['default_timezone' => 'Africa/Cairo']);
        $this->actor = User::factory()->inOrganization($this->organization->id)->create();
        foreach (['admin.panel.access', 'session.view', 'student.view.any', 'schedule.view', 'schedule.manage'] as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->id === $this->actor->id);
        }
        $this->actingAs($this->actor);
        $this->program = Program::factory()->create(['organization_id' => $this->organization->id]);
        $level = Level::factory()->create(['program_id' => $this->program->id]);
        $this->course = Course::factory()->create(['organization_id' => $this->organization->id, 'level_id' => $level->id, 'session_mode' => SessionMode::Group]);
        $teacherUser = User::factory()->inOrganization($this->organization->id)->create(['name' => 'معلم المجموعة']);
        $this->teacher = StaffProfile::query()->create(['organization_id' => $this->organization->id, 'user_id' => $teacherUser->id, 'staff_code' => 'T-GRP', 'employment_type' => 'contractor', 'hired_at' => '2026-01-01']);
        DB::table('teacher_courses')->insert(['id' => (string) Str::ulid(), 'staff_profile_id' => $this->teacher->id, 'course_id' => $this->course->id, 'qualified_at' => now(), 'qualified_by' => $this->actor->id, 'created_at' => now(), 'updated_at' => now()]);
        TeacherContract::query()->create(['organization_id' => $this->organization->id, 'staff_profile_id' => $this->teacher->id, 'basis' => ContractBasis::PerSession, 'effective_from' => CarbonImmutable::now('UTC')->subMonths(2)->toDateString(), 'currency' => 'EGP']);
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            TeacherAvailability::query()->create(['staff_profile_id' => $this->teacher->id, 'weekday' => $weekday, 'start_time' => '00:00:00', 'end_time' => '23:59:59', 'timezone' => 'Europe/Paris', 'effective_from' => '2026-08-01', 'approval_status' => TeacherAvailabilityApprovalStatus::Approved, 'approved_at' => CarbonImmutable::now('UTC')->subMonths(2)]);
        }
        $this->group = Group::query()->create(['organization_id' => $this->organization->id, 'code' => 'GR-RATE', 'name' => ['ar' => 'مجموعة السعر'], 'capacity' => 12, 'timezone' => 'Europe/Paris', 'status' => GroupStatus::Active, 'starts_on' => '2026-10-01']);
        GroupProgram::query()->create(['group_id' => $this->group->id, 'program_id' => $this->program->id]);
        GroupTeacher::query()->create(['group_id' => $this->group->id, 'staff_profile_id' => $this->teacher->id, 'course_id' => $this->course->id, 'role' => GroupTeacherRole::Lead, 'assigned_from' => '2026-10-01']);
    }

    public function test_a_priced_group_duration_still_needs_no_teacher_rate(): void
    {
        $priced = (int) (config('scheduling.session_durations')[0] ?? 30);

        $this->post('/manage/schedules', $this->payload($priced))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($priced, (int) Schedule::query()->where('group_id', $this->group->id)->value('duration_minutes'));
        $this->assertDatabaseCount('teacher_rates', 0);
    }

    public function test_a_custom_group_duration_is_refused_while_the_teacher_has_no_rate(): void
    {
        $this->post('/manage/schedules', $this->payload())
            ->assertSessionHasErrors('form');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_a_custom_group_duration_is_accepted_once_a_rate_is_sent_with_it(): void
    {
        $this->post('/manage/schedules', [
            ...$this->payload(),
            'session_rate_major' => '52.00',
            'rate_reason' => 'سعر حصة المجموعة المتفق عليه مع المعلم',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(self::CUSTOM_DURATION, (int) Schedule::query()->where('group_id', $this->group->id)->value('duration_minutes'));
        $this->assertDatabaseHas('teacher_rates', [
            'scope' => RateScope::Course->value,
            'course_id' => $this->course->id,
            'program_id' => $this->program->id,
            'amount' => 5200,
            'effective_to' => null,
        ]);
    }

    public function test_the_rate_reason_is_required_when_setting_a_rate(): void
    {
        $this->post('/manage/schedules', [
            ...$this->payload(),
            'session_rate_major' => '52.00',
        ])->assertSessionHasErrors('rate_reason');

        $this->assertDatabaseCount('schedules', 0);
        $this->assertDatabaseCount('teacher_rates', 0);
    }

    public function test_a_duration_outside_the_configured_limits_is_refused(): void
    {
        $this->post('/manage/schedules', $this->payload((int) config('session_pay.min_duration') - 1))
            ->assertSessionHasErrors('duration_minutes');

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_the_rate_endpoint_reports_the_course_rate_for_the_selected_teacher(): void
    {
        $this->getJson('/manage/schedules/rate?course_id='.$this->course->id.'&staff_profile_id='.$this->teacher->id)
            ->assertOk()->assertJsonPath('rate_major', null)->assertJsonPath('requires_rate', true);

        $this->post('/manage/schedules', [
            ...$this->payload(),
            'session_rate_major' => '52.00',
            'rate_reason' => 'سعر حصة المجموعة المتفق عليه مع المعلم',
        ])->assertSessionHasNoErrors();

        // السعر يسري من تاريخ بداية الجدول (2026-10-18)، فلا يظهر "الآن" إلا بعده.
        $this->travelTo(CarbonImmutable::parse('2026-10-18 09:00 UTC'));
        $this->getJson('/manage/schedules/rate?course_id='.$this->course->id.'&staff_profile_id='.$this->teacher->id)
            ->assertOk()->assertJsonPath('rate_major', '52.00');
    }

    /** @return array<string, mixed> */
    private function payload(int $duration = self::CUSTOM_DURATION): array
    {
        return [
            'group_id' => $this->group->id, 'course_id' => $this->course->id, 'staff_profile_id' => $this->teacher->id,
            'weekdays' => [0], 'interval_weeks' => 1, 'start_time' => '09:00', 'duration_minutes' => $duration,
            'timezone' => 'Europe/Paris', 'starts_on' => '2026-10-18', 'ends_on' => '2026-11-15',
        ];
    }
}

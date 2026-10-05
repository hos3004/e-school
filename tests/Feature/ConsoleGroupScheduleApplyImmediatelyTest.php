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
use Modules\Notifications\Application\Services\NotificationRecipientSilencer;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Domain\Models\TeacherContract;
use Tests\TestCase;

/**
 * التطبيق الفوري لتعديل جدول المجموعة يتجاوز مهلة حماية الحصص القريبة.
 *
 * المحروس: بدونه تبقى حصة اليوم/الغد بموعدها القديم أو لا تُولَّد أصلًا (سلوك
 * مقصود)، ومعه وبسبب صريح تُعاد جدولتها، مع تسجيله في التدقيق، وللمخوَّل فقط.
 */
final class ConsoleGroupScheduleApplyImmediatelyTest extends TestCase
{
    use RefreshDatabase;

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
        $this->group = Group::query()->create(['organization_id' => $this->organization->id, 'code' => 'GR-IMM', 'name' => ['ar' => 'مجموعة التطبيق الفوري'], 'capacity' => 12, 'timezone' => 'Europe/Paris', 'status' => GroupStatus::Active, 'starts_on' => '2026-10-01']);
        GroupProgram::query()->create(['group_id' => $this->group->id, 'program_id' => $this->program->id]);
        GroupTeacher::query()->create(['group_id' => $this->group->id, 'staff_profile_id' => $this->teacher->id, 'course_id' => $this->course->id, 'role' => GroupTeacherRole::Lead, 'assigned_from' => '2026-10-01']);
    }

    public function test_a_plain_edit_leaves_a_protected_near_term_session_untouched(): void
    {
        [$schedule, $protected] = $this->scheduleWithProtectedSession();

        $this->patch('/manage/schedules/'.$schedule->id, $this->payload('10:00'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sessions', ['id' => $protected->id, 'status' => 'scheduled']);
        $this->assertDatabaseMissing('sessions', ['schedule_id' => $schedule->id, 'scheduled_start' => '2026-10-18 08:00:00', 'status' => 'scheduled']);
    }

    public function test_apply_immediately_requires_a_reason_then_reschedules_the_protected_session_and_audits_it(): void
    {
        [$schedule, $protected] = $this->scheduleWithProtectedSession();

        $this->patch('/manage/schedules/'.$schedule->id, [...$this->payload('10:00'), 'apply_immediately' => true])
            ->assertSessionHasErrors('override_reason');
        $this->assertDatabaseHas('sessions', ['id' => $protected->id, 'status' => 'scheduled']);

        $reason = 'تعديل الموعد من اليوم بطلب المعلمة';
        $this->patch('/manage/schedules/'.$schedule->id, [...$this->payload('10:00'), 'apply_immediately' => true, 'override_reason' => $reason])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sessions', ['id' => $protected->id, 'status' => 'superseded']);
        $this->assertDatabaseHas('sessions', ['schedule_id' => $schedule->id, 'scheduled_start' => '2026-10-18 08:00:00', 'status' => 'scheduled']);
        $audit = DB::table('audit_log')->where('action', 'scheduling.schedule_updated')->orderByDesc('created_at')->first();
        $this->assertNotNull($audit);
        $this->assertSame($reason, $audit->reason);
        $this->assertTrue((bool) (json_decode((string) $audit->new_values, true)['applied_immediately'] ?? false));
    }

    public function test_apply_immediately_is_refused_without_the_manage_permission(): void
    {
        [$schedule, $protected] = $this->scheduleWithProtectedSession();
        Gate::define('schedule.manage', static fn (): bool => false);

        $this->patch('/manage/schedules/'.$schedule->id, [...$this->payload('10:00'), 'apply_immediately' => true, 'override_reason' => 'سبب عاجل'])
            ->assertForbidden();

        $this->assertDatabaseHas('sessions', ['id' => $protected->id, 'status' => 'scheduled']);
    }

    public function test_notifications_are_sent_by_default_and_each_party_can_be_silenced_for_one_edit(): void
    {
        [$schedule] = $this->scheduleWithProtectedSession();

        $this->patch('/manage/schedules/'.$schedule->id, $this->payload('10:00'))->assertSessionHasNoErrors();
        $this->assertSame([], app(NotificationRecipientSilencer::class)->silencedRoles());

        $this->patch('/manage/schedules/'.$schedule->id, [...$this->payload('11:00'), 'notify_student' => false, 'notify_teacher' => false])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['student', 'teacher'], app(NotificationRecipientSilencer::class)->silencedRoles());
    }

    public function test_apply_immediately_is_not_accepted_when_creating_a_schedule(): void
    {
        $this->post('/manage/schedules', [...$this->payload('09:00'), 'apply_immediately' => true, 'override_reason' => 'سبب'])
            ->assertSessionHasErrors('apply_immediately');

        $this->assertDatabaseCount('schedules', 0);
    }

    /** @return array{0: Schedule, 1: Session} */
    private function scheduleWithProtectedSession(): array
    {
        $this->post('/manage/schedules', $this->payload('09:00'))->assertSessionHasNoErrors();
        $schedule = Schedule::query()->where('group_id', $this->group->id)->firstOrFail();
        // الأحد 18 أكتوبر 09:00 باريس = 07:00 UTC، أي داخل مهلة الـ48 ساعة من الآن.
        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00 UTC'));
        $protected = Session::query()->where('schedule_id', $schedule->id)->where('scheduled_start', '2026-10-18 07:00:00')->firstOrFail();

        return [$schedule, $protected];
    }

    /** @return array<string, mixed> */
    private function payload(string $startTime): array
    {
        return [
            'group_id' => $this->group->id, 'course_id' => $this->course->id, 'staff_profile_id' => $this->teacher->id,
            'weekdays' => [0], 'interval_weeks' => 1, 'start_time' => $startTime,
            'duration_minutes' => (int) (config('scheduling.session_durations')[0] ?? 30),
            'timezone' => 'Europe/Paris', 'starts_on' => '2026-10-18', 'ends_on' => '2026-11-15',
        ];
    }
}

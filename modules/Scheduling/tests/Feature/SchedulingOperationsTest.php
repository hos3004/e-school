<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Events\StudentAssignedToGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Application\Actions\ApprovePostponement;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\RequestPostponement;
use Modules\Scheduling\Application\Actions\UpdateScheduleAction;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('creates a recurring group schedule with real sessions participants audit and DST-safe local time', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0],
        'start_time' => '09:00',
        'timezone' => 'Europe/Paris',
        'starts_on' => '2026-10-18',
        'ends_on' => '2026-11-15',
    ]);

    $sessions = Session::query()
        ->where('schedule_id', $schedule->id)
        ->orderBy('scheduled_start')
        ->get();

    $newStudentUser = User::factory()->inOrganization((string) $fixture['organization']->id)->create();
    $newStudent = StudentProfile::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $newStudentUser->id,
    ]);
    $newEnrollment = Enrollment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'student_profile_id' => $newStudent->id,
        'program_id' => $fixture['program']->id,
        'current_level_id' => $fixture['level']->id,
        'status' => EnrollmentStatus::Active,
        'applied_at' => now('UTC')->subMonth(),
        'activated_at' => now('UTC'),
    ]);
    $newMembership = GroupMembership::query()->create([
        'group_id' => $fixture['group']->id,
        'student_profile_id' => $newStudent->id,
        'joined_at' => now('UTC'),
        'status' => MembershipStatus::Active,
    ]);
    event(new StudentAssignedToGroup(
        membershipId: (string) $newMembership->id,
        groupId: (string) $fixture['group']->id,
        organizationId: (string) $fixture['organization']->id,
        studentProfileId: (string) $newStudent->id,
        studentUserId: (string) $newStudentUser->id,
        teacherUserIds: [],
        programId: (string) $fixture['program']->id,
        courseId: (string) $fixture['course']->id,
        actorId: (string) $fixture['operator']->id,
        reason: 'تسكين الطالب الجديد في المجموعة',
    ));

    expect($sessions)->toHaveCount(5)
        ->and($sessions->every(fn (Session $session): bool => $session->scheduled_start->setTimezone('Europe/Paris')->format('H:i') === '09:00'))->toBeTrue()
        ->and($sessions->first()->scheduled_start->format('H:i'))->toBe('07:00')
        ->and($sessions->get(2)->scheduled_start->format('H:i'))->toBe('08:00')
        ->and($newEnrollment->status)->toBe(EnrollmentStatus::Active)
        ->and(DB::table('session_participants')->whereIn('session_id', $sessions->pluck('id'))->count())->toBe(10)
        ->and(AuditLog::query()->where('action', 'scheduling.schedule_created')->where('auditable_id', $schedule->id)->exists())->toBeTrue();
});

it('blocks teacher conflicts and preserves the locked window when a template is edited', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [6, 0],
        'start_time' => '12:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-10',
        'ends_on' => '2026-11-10',
    ]);
    $lockedSession = Session::query()
        ->where('schedule_id', $schedule->id)
        ->where('scheduled_start', '<', CarbonImmutable::now('UTC')->addHours(48))
        ->firstOrFail();

    $secondGroup = Group::query()->create([
        'organization_id' => $fixture['organization']->id,
        'code' => 'GR-CONFLICT',
        'name' => ['ar' => 'مجموعة التعارض'],
        'capacity' => 12,
        'timezone' => 'UTC',
        'status' => GroupStatus::Active,
        'starts_on' => '2026-10-10',
    ]);
    GroupProgram::query()->create(['group_id' => $secondGroup->id, 'program_id' => $fixture['program']->id]);
    GroupTeacher::query()->create([
        'group_id' => $secondGroup->id,
        'staff_profile_id' => $fixture['teacher']->id,
        'course_id' => $fixture['course']->id,
        'role' => GroupTeacherRole::Lead,
        'assigned_from' => '2026-10-01',
    ]);

    expect(fn () => app(CreateScheduleAction::class)->execute(
        (string) $fixture['organization']->id,
        schedulePayload($fixture, [
            'group_id' => (string) $secondGroup->id,
            'weekdays' => [0],
            'start_time' => '12:00',
            'starts_on' => '2026-10-11',
            'ends_on' => '2026-11-10',
        ]),
        (string) $fixture['operator']->id,
        'اختبار منع حجز المعلم مرتين',
    ))->toThrow(BusinessRuleViolation::class);

    app(UpdateScheduleAction::class)->execute(
        $schedule,
        schedulePayload($fixture, [
            'weekdays' => [1],
            'start_time' => '14:00',
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-11-10',
        ]),
        (string) $fixture['operator']->id,
        'نقل الحصص المستقبلية بعد نافذة الحماية',
    );

    expect($lockedSession->fresh()->status)->toBe(SessionStatus::Scheduled)
        ->and(Session::query()->where('schedule_id', $schedule->id)->where('status', SessionStatus::Superseded)->exists())->toBeTrue()
        ->and(Session::query()
            ->where('schedule_id', $schedule->id)
            ->where('status', SessionStatus::Scheduled)
            ->where('scheduled_start', '>=', CarbonImmutable::now('UTC')->addHours(48))
            ->get()
            ->every(fn (Session $session): bool => $session->scheduled_start->format('H:i') === '14:00'))->toBeTrue();
});

it('runs the postponement lifecycle through the Sessions gateway and escalates monthly overflow', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    config()->set('scheduling.postponement.max_per_student_per_month', 1);
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0, 2],
        'start_time' => '10:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-10-31',
    ]);
    $sessions = Session::query()->where('schedule_id', $schedule->id)->orderBy('scheduled_start')->get();

    $request = app(RequestPostponement::class)->execute(
        (string) $fixture['organization']->id,
        (string) $sessions[0]->id,
        (string) $fixture['operator']->id,
        (string) $fixture['student']->id,
        $sessions[0]->scheduled_start->addDay(),
        'الطالب لديه اختبار في الموعد الأصلي',
    );
    $overflow = app(RequestPostponement::class)->execute(
        (string) $fixture['organization']->id,
        (string) $sessions[1]->id,
        (string) $fixture['operator']->id,
        (string) $fixture['student']->id,
        $sessions[1]->scheduled_start->addDay(),
        'طلب ثانٍ يحتاج مراجعة الإدارة',
    );

    expect($request->requires_admin_review)->toBeFalse()
        ->and($overflow->requires_admin_review)->toBeTrue();

    $approved = app(ApprovePostponement::class)->execute(
        (string) $fixture['organization']->id,
        (string) $request->id,
        (string) $fixture['operator']->id,
        $sessions[0]->scheduled_start->addDays(3),
        'اعتماد موعد التلافي بعد التحقق من التعارضات',
    );
    $makeup = Session::query()->findOrFail($approved->makeup_session_id);

    expect($approved->status)->toBe(PostponementStatus::Scheduled)
        ->and($sessions[0]->fresh()->status)->toBe(SessionStatus::Postponed)
        ->and($makeup->makeup_for_session_id)->toBe((string) $sessions[0]->id)
        ->and($makeup->participants()->pluck('student_profile_id')->all())->toBe([(string) $fixture['student']->id])
        ->and(AuditLog::query()->where('action', 'scheduling.postponement_approved')->exists())->toBeTrue();

    $other = Organization::factory()->create();
    expect(fn () => app(ApprovePostponement::class)->execute(
        (string) $other->id,
        (string) $overflow->id,
        (string) $fixture['operator']->id,
        $sessions[1]->scheduled_start->addDays(4),
        'محاولة عابرة للمؤسسات',
    ))->toThrow(BusinessRuleViolation::class);
});

it('rolls the scheduling integrity migration down and reapplies it cleanly', function (): void {
    $migration = require base_path('modules/Scheduling/database/migrations/2026_08_24_190000_harden_scheduling_integrity.php');

    $migration->down();
    expect(Schema::hasColumn('postponement_requests', 'organization_id'))->toBeFalse()
        ->and(Schema::hasColumn('postponement_requests', 'requires_admin_review'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('postponement_requests', 'organization_id'))->toBeTrue()
        ->and(Schema::hasColumn('postponement_requests', 'requires_admin_review'))->toBeTrue();
});

/** @return array<string, object> */
function schedulingFixture(): array
{
    $organization = Organization::factory()->create();
    $operator = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'مدير الجدولة']);
    $teacherUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'المعلم التشغيلي']);
    $studentUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'الطالب التشغيلي']);
    $program = Program::factory()->create(['organization_id' => $organization->id]);
    $level = Level::factory()->create(['program_id' => $program->id]);
    $course = Course::factory()->create([
        'organization_id' => $organization->id,
        'level_id' => $level->id,
        'session_mode' => SessionMode::Group,
        'name' => ['ar' => 'كورس الجدولة الحقيقي', 'en' => 'Scheduling Course'],
    ]);
    $teacher = StaffProfile::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $teacherUser->id,
        'staff_code' => 'T-SCHEDULE',
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Male,
        'hired_at' => '2026-01-01',
    ]);
    DB::table('teacher_courses')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $teacher->id,
        'course_id' => $course->id,
        'qualified_at' => now('UTC'),
        'qualified_by' => $operator->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);
    $student = StudentProfile::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $studentUser->id,
        'student_code' => 'ST-SCHEDULE',
    ]);
    Enrollment::query()->create([
        'organization_id' => $organization->id,
        'student_profile_id' => $student->id,
        'program_id' => $program->id,
        'current_level_id' => $level->id,
        'status' => EnrollmentStatus::Active,
        'applied_at' => now('UTC')->subMonth(),
        'activated_at' => now('UTC')->subWeeks(2),
    ]);
    $group = Group::query()->create([
        'organization_id' => $organization->id,
        'code' => 'GR-SCHEDULE',
        'name' => ['ar' => 'مجموعة الجدولة الحقيقية', 'en' => 'Scheduling Group'],
        'capacity' => 12,
        'timezone' => 'UTC',
        'status' => GroupStatus::Active,
        'starts_on' => '2026-10-01',
    ]);
    GroupProgram::query()->create(['group_id' => $group->id, 'program_id' => $program->id]);
    GroupTeacher::query()->create([
        'group_id' => $group->id,
        'staff_profile_id' => $teacher->id,
        'course_id' => $course->id,
        'role' => GroupTeacherRole::Lead,
        'assigned_from' => '2026-10-01',
    ]);
    GroupMembership::query()->create([
        'group_id' => $group->id,
        'student_profile_id' => $student->id,
        'joined_at' => now('UTC')->subDay(),
        'status' => MembershipStatus::Active,
    ]);

    return compact('organization', 'operator', 'teacher', 'student', 'program', 'level', 'course', 'group');
}

/**
 * @param array<string, object> $fixture
 * @param array<string, mixed> $overrides
 */
function createOperationalSchedule(array $fixture, array $overrides = []): Schedule
{
    return app(CreateScheduleAction::class)->execute(
        (string) $fixture['organization']->id,
        schedulePayload($fixture, $overrides),
        (string) $fixture['operator']->id,
        'إنشاء قالب جدول تشغيلي كامل للاختبار',
    );
}

/**
 * @param array<string, object> $fixture
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function schedulePayload(array $fixture, array $overrides = []): array
{
    return [
        'target_type' => 'group',
        'group_id' => (string) $fixture['group']->id,
        'course_id' => (string) $fixture['course']->id,
        'staff_profile_id' => (string) $fixture['teacher']->id,
        'weekdays' => [0],
        'interval_weeks' => 1,
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
        ...$overrides,
    ];
}

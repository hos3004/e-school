<?php

declare(strict_types=1);

/*
| تغيير الموعد الدائم بطلب المعلم.
|
| يعتمد هذا الملف على schedulingFixture() و createOperationalSchedule() المعرّفتين
| في SchedulingOperationsTest.php ضمن نفس المجلد؛ شغّل المجلد كاملًا:
|   php scripts/test-isolated.php modules/Scheduling/tests/Feature
*/

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Scheduling\Application\Actions\RequestScheduleChange;
use Modules\Scheduling\Application\Actions\RespondToScheduleChange;
use Modules\Scheduling\Domain\Enums\ScheduleChangeApprovalStatus;
use Modules\Scheduling\Domain\Enums\ScheduleChangeStatus;
use Modules\Scheduling\Domain\Models\ScheduleChangeRequest;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * يضيف طالبًا ثانيًا نشطًا لنفس المجموعة حتى نثبت شرط «قبول كل الطلاب».
 *
 * @param array<string, object> $fixture
 */
function scheduleChangeSecondStudent(array $fixture): StudentProfile
{
    $user = User::factory()->inOrganization((string) $fixture['organization']->id)->create([
        'name' => 'الطالب الثاني',
    ]);
    $student = StudentProfile::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $user->id,
        'student_code' => 'ST-SCHEDULE-2',
    ]);
    Enrollment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'student_profile_id' => $student->id,
        'program_id' => $fixture['program']->id,
        'current_level_id' => $fixture['level']->id,
        'status' => EnrollmentStatus::Active,
        'applied_at' => now('UTC')->subMonth(),
        'activated_at' => now('UTC')->subWeeks(2),
    ]);
    GroupMembership::query()->create([
        'group_id' => $fixture['group']->id,
        'student_profile_id' => $student->id,
        'joined_at' => now('UTC')->subDay(),
        'status' => MembershipStatus::Active,
    ]);

    return $student;
}

/** يمنح مستخدمًا دورًا نظاميًا مبذورًا. */
function scheduleChangeAssignRole(User $user, string $role): void
{
    $roleId = DB::table('roles')->whereNull('organization_id')
        ->where('guard_name', 'web')->where('name', $role)->value('id');

    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_type' => User::class,
        'model_id' => $user->id,
    ]);
}

it('records a teacher request without touching the schedule and notifies students supervisor and admin', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $this->seed(AccessControlSeeder::class);
    $this->seed(NotificationTemplateSeeder::class);
    $secondStudent = scheduleChangeSecondStudent($fixture);

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0],
        'start_time' => '10:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
    ]);

    $supervisor = User::factory()->inOrganization((string) $fixture['organization']->id)->create(['name' => 'المشرف']);
    $admin = User::factory()->inOrganization((string) $fixture['organization']->id)->create(['name' => 'الأدمن']);
    scheduleChangeAssignRole($supervisor, 'academic_supervisor');
    scheduleChangeAssignRole($admin, 'platform_admin');

    $teacherUser = User::query()->findOrFail(
        DB::table('staff_profiles')->where('id', $fixture['teacher']->id)->value('user_id'),
    );

    $request = app(RequestScheduleChange::class)->execute(
        schedule: $schedule,
        staffProfileId: (string) $fixture['teacher']->id,
        requestedBy: (string) $teacherUser->id,
        proposedSlots: [['weekday' => 2, 'start_time' => '14:00']],
        intervalWeeks: null,
        reason: 'تعارض ثابت مع التزام أسبوعي جديد',
    );

    $recipients = NotificationOutbox::query()
        ->where('category', 'schedule_change_request')
        ->pluck('user_id')
        ->unique()
        ->values()
        ->all();

    expect($request->status)->toBe(ScheduleChangeStatus::Pending)
        ->and($request->approvals()->count())->toBe(2)
        ->and($request->approvals()->where('status', ScheduleChangeApprovalStatus::Pending->value)->count())->toBe(2)
        ->and($schedule->refresh()->rrule)->toContain('BYDAY=SU')
        ->and(substr((string) $schedule->start_time, 0, 5))->toBe('10:00')
        ->and($recipients)->toContain((string) $supervisor->id)
        ->and($recipients)->toContain((string) $admin->id)
        ->and($recipients)->toContain((string) $teacherUser->id)
        ->and($recipients)->toContain((string) $secondStudent->user_id)
        ->and(AuditLog::query()->where('action', 'scheduling.schedule_change_requested')->count())->toBe(1);
});

it('applies the new weekly time only after every student accepts and keeps the locked window', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $this->seed(AccessControlSeeder::class);
    $secondStudent = scheduleChangeSecondStudent($fixture);

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0],
        'start_time' => '10:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
    ]);
    $lockedSession = Session::query()
        ->where('schedule_id', $schedule->id)
        ->orderBy('scheduled_start')
        ->first();

    $teacherUser = User::query()->findOrFail(
        DB::table('staff_profiles')->where('id', $fixture['teacher']->id)->value('user_id'),
    );
    $request = app(RequestScheduleChange::class)->execute(
        schedule: $schedule,
        staffProfileId: (string) $fixture['teacher']->id,
        requestedBy: (string) $teacherUser->id,
        proposedSlots: [['weekday' => 2, 'start_time' => '14:00']],
        intervalWeeks: null,
        reason: 'موعد أنسب لكل الطلاب',
    );

    app(RespondToScheduleChange::class)->execute(
        request: $request,
        studentProfileId: (string) $fixture['student']->id,
        respondedBy: (string) $fixture['student']->user_id,
        accepted: true,
    );

    expect($request->refresh()->status)->toBe(ScheduleChangeStatus::Pending)
        ->and($schedule->refresh()->rrule)->toContain('BYDAY=SU');

    app(RespondToScheduleChange::class)->execute(
        request: $request,
        studentProfileId: (string) $secondStudent->id,
        respondedBy: (string) $secondStudent->user_id,
        accepted: true,
    );

    $schedule->refresh();
    $futureWeekdays = Session::query()
        ->where('schedule_id', $schedule->id)
        ->where('scheduled_start', '>=', CarbonImmutable::now('UTC')->addHours(48))
        ->where('status', SessionStatus::Scheduled->value)
        ->get()
        ->map(static fn (Session $session): int => (int) $session->scheduled_start->dayOfWeek)
        ->unique()
        ->values()
        ->all();

    expect($request->refresh()->status)->toBe(ScheduleChangeStatus::Applied)
        ->and($request->applied_at)->not->toBeNull()
        ->and($schedule->rrule)->toContain('BYDAY=TU')
        ->and(substr((string) $schedule->start_time, 0, 5))->toBe('14:00')
        ->and($futureWeekdays)->toBe([2])
        ->and(Session::query()->findOrFail((string) $lockedSession?->id)->scheduled_start->format('H:i'))->toBe('10:00')
        ->and(AuditLog::query()->where('action', 'scheduling.schedule_updated')->count())->toBe(1);
});

it('ends the request when one student declines and leaves the schedule untouched', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $this->seed(AccessControlSeeder::class);
    $secondStudent = scheduleChangeSecondStudent($fixture);

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0],
        'start_time' => '10:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
    ]);
    $teacherUser = User::query()->findOrFail(
        DB::table('staff_profiles')->where('id', $fixture['teacher']->id)->value('user_id'),
    );
    $request = app(RequestScheduleChange::class)->execute(
        schedule: $schedule,
        staffProfileId: (string) $fixture['teacher']->id,
        requestedBy: (string) $teacherUser->id,
        proposedSlots: [['weekday' => 2, 'start_time' => '14:00']],
        intervalWeeks: null,
        reason: 'محاولة تغيير الموعد الدائم',
    );

    app(RespondToScheduleChange::class)->execute(
        request: $request,
        studentProfileId: (string) $fixture['student']->id,
        respondedBy: (string) $fixture['student']->user_id,
        accepted: true,
    );
    app(RespondToScheduleChange::class)->execute(
        request: $request,
        studentProfileId: (string) $secondStudent->id,
        respondedBy: (string) $secondStudent->user_id,
        accepted: false,
        note: 'الموعد الجديد يتعارض مع مدرستي',
    );

    expect($request->refresh()->status)->toBe(ScheduleChangeStatus::Rejected)
        ->and($schedule->refresh()->rrule)->toContain('BYDAY=SU')
        ->and(substr((string) $schedule->start_time, 0, 5))->toBe('10:00');

    app(RespondToScheduleChange::class)->execute(
        request: $request,
        studentProfileId: (string) $fixture['student']->id,
        respondedBy: (string) $fixture['student']->user_id,
        accepted: true,
    );
})->throws(BusinessRuleViolation::class);

it('refuses a request for a schedule assigned to another teacher and an identical weekly time', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $this->seed(AccessControlSeeder::class);

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0],
        'start_time' => '10:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
    ]);
    $teacherUser = User::query()->findOrFail(
        DB::table('staff_profiles')->where('id', $fixture['teacher']->id)->value('user_id'),
    );
    $action = app(RequestScheduleChange::class);

    expect(fn () => $action->execute(
        schedule: $schedule,
        staffProfileId: (string) $fixture['student']->id,
        requestedBy: (string) $teacherUser->id,
        proposedSlots: [['weekday' => 2, 'start_time' => '14:00']],
        intervalWeeks: null,
        reason: 'معلم آخر يحاول تغيير الجدول',
    ))->toThrow(BusinessRuleViolation::class);

    expect(fn () => $action->execute(
        schedule: $schedule,
        staffProfileId: (string) $fixture['teacher']->id,
        requestedBy: (string) $teacherUser->id,
        proposedSlots: [['weekday' => 0, 'start_time' => '10:00']],
        intervalWeeks: null,
        reason: 'نفس الموعد الحالي',
    ))->toThrow(BusinessRuleViolation::class);

    expect(fn () => $action->execute(
        schedule: $schedule,
        staffProfileId: (string) $fixture['teacher']->id,
        requestedBy: (string) $teacherUser->id,
        proposedSlots: [['weekday' => 0, 'start_time' => '14:00'], ['weekday' => 2, 'start_time' => '16:00']],
        intervalWeeks: null,
        reason: 'ساعتان مختلفتان لحصة جماعية',
    ))->toThrow(BusinessRuleViolation::class);

    expect(ScheduleChangeRequest::query()->count())->toBe(0);
});

it('lets the teacher submit and the student respond over the learning portal with real permissions', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    config(['console.enabled' => true]);
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $this->seed(AccessControlSeeder::class);
    app(PermissionGateRegistrar::class)->register();
    $this->withoutVite();

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0],
        'start_time' => '10:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
    ]);

    $teacherUser = User::query()->findOrFail(
        DB::table('staff_profiles')->where('id', $fixture['teacher']->id)->value('user_id'),
    );
    $studentUser = User::query()->findOrFail((string) $fixture['student']->user_id);
    scheduleChangeAssignRole($teacherUser, 'teacher');
    scheduleChangeAssignRole($studentUser, 'student');

    $this->actingAs($teacherUser, 'web')
        ->post("/learn/teacher/schedules/{$schedule->id}/change-requests", [
            'slots' => [['weekday' => 2, 'start_time' => '14:00']],
            'reason' => 'طلب تغيير الموعد الدائم من بوابة المعلم',
        ])
        ->assertRedirect();

    $request = ScheduleChangeRequest::query()->sole();

    $this->actingAs($teacherUser, 'web')
        ->post("/learn/student/schedule-changes/{$request->id}/respond", ['decision' => 'accept'])
        ->assertForbidden();

    $this->actingAs($studentUser, 'web')
        ->post("/learn/student/schedule-changes/{$request->id}/respond", ['decision' => 'accept'])
        ->assertRedirect();

    expect($request->refresh()->status)->toBe(ScheduleChangeStatus::Applied)
        ->and($schedule->refresh()->rrule)->toContain('BYDAY=TU');
});

it('rolls the schedule change migration down and reapplies it with its data intact', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0],
        'start_time' => '10:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
    ]);
    $teacherUser = User::query()->findOrFail(
        DB::table('staff_profiles')->where('id', $fixture['teacher']->id)->value('user_id'),
    );
    app(RequestScheduleChange::class)->execute(
        schedule: $schedule,
        staffProfileId: (string) $fixture['teacher']->id,
        requestedBy: (string) $teacherUser->id,
        proposedSlots: [['weekday' => 2, 'start_time' => '14:00']],
        intervalWeeks: null,
        reason: 'بيانات قبل اختبار الهجرة',
    );

    expect(ScheduleChangeRequest::query()->count())->toBe(1)
        ->and(DB::table('schedule_change_approvals')->count())->toBe(1);

    $migration = require base_path('modules/Scheduling/database/migrations/2026_09_10_160000_create_schedule_change_requests_tables.php');

    $migration->down();
    expect(Schema::hasTable('schedule_change_requests'))->toBeFalse()
        ->and(Schema::hasTable('schedule_change_approvals'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('schedule_change_requests'))->toBeTrue()
        ->and(Schema::hasTable('schedule_change_approvals'))->toBeTrue()
        ->and(Schema::hasColumn('schedule_change_requests', 'proposed_weekly_slots'))->toBeTrue()
        ->and(ScheduleChangeRequest::query()->count())->toBe(0);
});

it('grants the schedule change permissions to the system roles without resyncing the whole matrix', function (): void {
    $this->seed(AccessControlSeeder::class);

    $teacherRoleId = DB::table('roles')->whereNull('organization_id')
        ->where('guard_name', 'web')->where('name', 'teacher')->value('id');
    $customPermissionId = DB::table('permissions')->where('name', 'payroll.pay')->value('id');
    DB::table('role_has_permissions')->insert([
        'role_id' => $teacherRoleId,
        'permission_id' => $customPermissionId,
    ]);
    $permissionIds = DB::table('permissions')
        ->whereIn('name', ['schedule.change.request', 'schedule.change.respond'])
        ->pluck('id')
        ->all();
    DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
    DB::table('permissions')->whereIn('id', $permissionIds)->delete();

    $migration = require base_path('modules/AccessControl/database/migrations/2026_09_10_160100_grant_schedule_change_permissions.php');
    $migration->up();
    $migration->up();

    $granted = static fn (string $role, string $permission): int => DB::table('role_has_permissions')
        ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
        ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
        ->whereNull('roles.organization_id')
        ->where('roles.name', $role)
        ->where('permissions.name', $permission)
        ->count();

    expect($granted('teacher', 'schedule.change.request'))->toBe(1)
        ->and($granted('academic_supervisor', 'schedule.change.request'))->toBe(1)
        ->and($granted('registrar', 'schedule.change.request'))->toBe(1)
        ->and($granted('platform_admin', 'schedule.change.request'))->toBe(1)
        ->and($granted('student', 'schedule.change.respond'))->toBe(1)
        ->and($granted('teacher', 'schedule.change.respond'))->toBe(0)
        ->and($granted('teacher', 'payroll.pay'))->toBe(1);

    $migration->down();
    expect(DB::table('permissions')->whereIn('name', ['schedule.change.request', 'schedule.change.respond'])->count())->toBe(0)
        ->and($granted('teacher', 'payroll.pay'))->toBe(1);
});

it('shows the permanent time section to the teacher and the pending request to the student', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    config(['console.enabled' => true]);
    $fixture = schedulingFixture();
    $this->actingAs($fixture['operator']);
    $this->seed(AccessControlSeeder::class);
    app(PermissionGateRegistrar::class)->register();
    $this->withoutVite();

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [0],
        'start_time' => '10:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-11',
        'ends_on' => '2026-11-30',
    ]);
    $teacherUser = User::query()->findOrFail(
        DB::table('staff_profiles')->where('id', $fixture['teacher']->id)->value('user_id'),
    );
    $studentUser = User::query()->findOrFail((string) $fixture['student']->user_id);
    scheduleChangeAssignRole($teacherUser, 'teacher');
    scheduleChangeAssignRole($studentUser, 'student');

    $this->actingAs($teacherUser, 'web')->get('/learn/teacher/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Learning/Schedule')
            ->where('canRequestScheduleChange', true)
            ->has('permanentSchedules', 1)
            ->where('permanentSchedules.0.id', (string) $schedule->id)
            ->where('permanentSchedules.0.pendingRequest', null)
            ->has('permanentSchedules.0.currentSummary'));

    $this->actingAs($studentUser, 'web')->get('/learn/student/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Learning/Schedule')
            ->where('canRequestScheduleChange', false)
            ->has('scheduleChangeRequests', 0));

    app(RequestScheduleChange::class)->execute(
        schedule: $schedule,
        staffProfileId: (string) $fixture['teacher']->id,
        requestedBy: (string) $teacherUser->id,
        proposedSlots: [['weekday' => 2, 'start_time' => '14:00']],
        intervalWeeks: null,
        reason: 'عرض الطلب في بوابتي المعلم والطالب',
    );

    $this->actingAs($studentUser, 'web')->get('/learn/student/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->has('scheduleChangeRequests', 1)
            ->has('scheduleChangeRequests.0.proposedSummary')
            ->has('scheduleChangeRequests.0.currentSummary'));

    $this->actingAs($teacherUser, 'web')->get('/learn/teacher/schedule')
        ->assertInertia(fn (Assert $page) => $page
            ->where('permanentSchedules.0.pendingRequest.status', 'pending')
            ->where('permanentSchedules.0.pendingRequest.acceptedCount', 0)
            ->where('permanentSchedules.0.pendingRequest.totalCount', 1));
});

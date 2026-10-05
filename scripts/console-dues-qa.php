<?php

declare(strict_types=1);

/** Manual QA fixture only. Never a migration or a production seeder. */
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Payroll\Application\Actions\ProposePayrollAdjustmentAction;
use Modules\Payroll\Application\Actions\RecordPayrollEntryAction;
use Modules\Payroll\Application\Services\PayrollPeriodResolver;
use Modules\Payroll\Domain\Events\PayrollAdjustmentProposed;
use Modules\Payroll\Domain\Events\PayrollEntryRecorded;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Models\StaffProfile;
use Shared\ValueObjects\Money;
use Shared\ValueObjects\TimeRange;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (!$app->environment('local') || config('database.connections.'.config('database.default').'.database') !== 'eschool_console_development'
    || DB::selectOne('select current_database() as name')->name !== 'eschool_console_development') {
    throw new RuntimeException('QA fixture requires local environment and the isolated console development database.');
}
app(PermissionGateRegistrar::class)->register();
$actors = User::query()->whereNotNull('organization_id')->get()->filter(static fn (User $user): bool => $user->can('admin.panel.access') && $user->can('payroll.view'));
if ($actors->pluck('organization_id')->unique()->count() !== 1) {
    throw new RuntimeException('QA fixture requires exactly one local administrative organization.');
}
$actor = $actors->firstOrFail();
$org = (string) $actor->organization_id;
$options = getopt('', ['program:', 'course:', 'inspect']);
$programs = Program::query()->forOrganization($org)->active()->get();
if (array_key_exists('inspect', $options)) {
    echo json_encode(['organizationId' => $org, 'actorId' => $actor->id, 'programs' => $programs->map(static fn (Program $p): array => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name])->all()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
    exit(0);
}
$program = isset($options['program']) ? $programs->firstWhere('id', $options['program']) : $programs->first(static fn (Program $p): bool => str_contains($p->name['ar'] ?? '', 'عام'));
if (isset($options['program']) && !$program) {
    throw new RuntimeException('Requested program is not active in the local organization.');
}
$placementCourse = isset($options['course']) ? Course::query()->forOrganization($org)->active()->findOrFail($options['course']) : null;
if ($placementCourse) {
    $placementProgram = $placementCourse->level?->program;
    if (!$placementProgram || $placementProgram->organization_id !== $org || !$placementProgram->is_active) {
        throw new RuntimeException('Placement course must belong to an active program in the same local organization.');
    }
    $program = $placementProgram;
}
// No mail, push, HTTP requests, queued work or financial event listeners can escape this process.
config(['mail.default' => 'log']);
Mail::fake();
Notification::fake();
Bus::fake();
Http::preventStrayRequests();
Event::fake([PayrollEntryRecorded::class, PayrollAdjustmentProposed::class]);
$app->setLocale('ar');
auth()->setUser($actor);
$result = DB::transaction(function () use ($org, $actor, $program, $placementCourse): array {
    $code = 'CONSOLE-DUES-QA';
    $existing = StaffProfile::query()->forOrganization($org)->where('staff_code', $code)->first();
    if ($existing) {
        $entry = PayrollEntry::query()->forOrganization($org)->where('staff_profile_id', $existing->id)->firstOrFail();

        return ['created' => false, 'teacherId' => $existing->id, 'periodId' => $entry->payroll_period_id,
            'courseId' => Course::query()->forOrganization($org)->where('code', $code)->value('id'),
            'groupId' => Group::query()->forOrganization($org)->where('code', $code)->value('id')];
    }
    $now = CarbonImmutable::now('UTC');
    $start = $now->startOfMonth()->addHours(12);
    $period = app(PayrollPeriodResolver::class)->forDate($org, $start);
    if (!$period->status->acceptsEntries() || !$period->status->acceptsAdjustments()) {
        throw new RuntimeException('Current local payroll period must be open; the fixture never reopens a closed period.');
    }
    $teacherUser = User::query()->create(['organization_id' => $org, 'name' => 'معلم تجربة المستحقات QA',
        'email' => 'console-dues-qa@school.invalid', 'password' => Hash::make(Str::random(64)), 'locale' => 'ar',
        'timezone' => 'Africa/Cairo', 'status' => 'active']);
    $teacher = StaffProfile::query()->create(['organization_id' => $org, 'user_id' => $teacherUser->id, 'staff_code' => $code,
        'employment_type' => EmploymentType::PartTime, 'hired_at' => $start->subMonth(), 'bio' => ['ar' => 'بيانات تجربة محلية؛ لا يمثل شخصًا حقيقيًا.']]);
    $contract = (string) Str::ulid();
    DB::table('teacher_contracts')->insert(['id' => $contract, 'organization_id' => $org, 'staff_profile_id' => $teacher->id,
        'basis' => 'per_session', 'currency' => config('payroll.currency'), 'effective_from' => $start->subMonth()->toDateString(),
        'created_at' => $now, 'updated_at' => $now]);
    DB::table('teacher_rates')->insert(['id' => (string) Str::ulid(), 'teacher_contract_id' => $contract,
        'scope' => 'default', 'amount' => 5000, 'currency' => config('payroll.currency'), 'effective_from' => $start->subMonth()->toDateString(), 'created_at' => $now]);
    $program ??= Program::query()->create(['organization_id' => $org, 'code' => $code, 'name' => ['ar' => 'برنامج تجربة المستحقات QA'],
        'default_session_minutes' => 60, 'currency' => config('payroll.currency'), 'is_active' => true]);
    $level = Level::query()->create(['program_id' => $program->id, 'code' => $code, 'name' => ['ar' => 'مستوى تجربة المستحقات QA'], 'sort_order' => 900]);
    $course = Course::query()->create(['organization_id' => $org, 'level_id' => $level->id, 'code' => $code,
        'name' => ['ar' => 'كورس مجموعات تجربة المستحقات QA'], 'session_mode' => SessionMode::Group,
        'is_active' => true, 'default_duration_minutes' => 60]);
    DB::table('teacher_courses')->insert(['id' => (string) Str::ulid(), 'staff_profile_id' => $teacher->id,
        'course_id' => $course->id, 'qualified_at' => $now, 'qualified_by' => $actor->id, 'notes' => 'أهلية تجربة محلية QA', 'created_at' => $now, 'updated_at' => $now]);
    $group = Group::query()->create(['organization_id' => $org, 'code' => $code, 'name' => ['ar' => 'مجموعة تجربة المستحقات QA'],
        'capacity' => 12, 'timezone' => 'Africa/Cairo', 'status' => GroupStatus::Active, 'starts_on' => $start->toDateString()]);
    GroupProgram::query()->create(['group_id' => $group->id, 'program_id' => $program->id]);
    GroupTeacher::query()->create(['group_id' => $group->id, 'staff_profile_id' => $teacher->id, 'course_id' => $course->id,
        'role' => GroupTeacherRole::Lead, 'assigned_from' => $start->toDateString()]);
    if ($placementCourse) {
        DB::table('teacher_courses')->insert(['id' => (string) Str::ulid(), 'staff_profile_id' => $teacher->id,
            'course_id' => $placementCourse->id, 'qualified_at' => $now, 'qualified_by' => $actor->id,
            'notes' => 'أهلية استكمال رحلة التسجيل المحلية QA', 'created_at' => $now, 'updated_at' => $now]);
        GroupTeacher::query()->create(['group_id' => $group->id, 'staff_profile_id' => $teacher->id, 'course_id' => $placementCourse->id,
            'role' => GroupTeacherRole::Lead, 'assigned_from' => $start->toDateString()]);
    }
    $sessions = [];
    foreach ([SessionStatus::Completed, SessionStatus::AwaitingReview, SessionStatus::CancelledBySchool] as $index => $status) {
        $date = $start->addDays($index);
        $session = Session::query()->create(['organization_id' => $org, 'course_id' => $course->id, 'group_id' => $group->id,
            'staff_profile_id' => $teacher->id, 'original_teacher_id' => $teacher->id, 'session_type' => 'regular', 'status' => $status,
            'scheduled_start' => $date, 'scheduled_end' => $date->addHour(), 'title' => ['ar' => 'حصة تجربة المستحقات QA '.($index + 1)],
            'actual_start' => $index < 2 ? $date : null, 'actual_end' => $index < 2 ? $date->addMinutes(50) : null]);
        $sessions[] = $session->id;
        if ($status === SessionStatus::Completed) {
            app(RecordPayrollEntryAction::class)->execute($org, $period->id, $teacher->id, $contract, 'session_earning', 'completed',
                Money::of(5000, (string) config('payroll.currency')), TimeRange::fromDuration($date, 60), sessionId: $session->id,
                resolvedVia: 'default', actorId: $actor->id);
        }
    }
    // This synthetic proposer enables the administrator to review the independent-approval UI.
    // No credentials or new permissions are issued to the fixture account.
    $bonus = app(ProposePayrollAdjustmentAction::class)->execute($org, $period->id, $teacher->id, 'bonus',
        Money::of(2510, (string) config('payroll.currency')), 'مكافأة تجربة محلية QA قابلة للمراجعة؛ لا تمثل صرفًا حقيقيًا.', actorId: $teacherUser->id);

    return ['created' => true, 'teacherId' => $teacher->id, 'periodId' => $period->id, 'programId' => $program->id,
        'courseId' => $course->id, 'placementCourseId' => $placementCourse?->id, 'groupId' => $group->id, 'sessions' => $sessions, 'bonusId' => $bonus->id];
});
$result['statementPath'] = '/manage/teacher-dues?period='.$result['periodId'].'&teacher='.$result['teacherId'];
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

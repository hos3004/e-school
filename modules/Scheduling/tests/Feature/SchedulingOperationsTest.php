<?php

declare(strict_types=1);

use App\Application\Actions\BulkCreateIndividualQuranSchedulesAction;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
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
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Application\Actions\ApprovePostponement;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\RequestPostponement;
use Modules\Scheduling\Application\Actions\UpdateScheduleAction;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages\CreateSchedule;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
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

it('creates an individual schedule and builds its notification from serialized session dates', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    $fixture = schedulingFixture();
    $individualCourse = Course::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'level_id' => $fixture['level']->id,
        'session_mode' => SessionMode::Individual,
        'name' => ['ar' => 'القرآن الفردي', 'en' => 'Individual Quran'],
    ]);
    DB::table('teacher_courses')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $fixture['teacher']->id,
        'course_id' => $individualCourse->id,
        'qualified_at' => now('UTC'),
        'qualified_by' => $fixture['operator']->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);
    TeacherAvailability::query()->create([
        'staff_profile_id' => $fixture['teacher']->id,
        'weekday' => 0,
        'start_time' => '09:00',
        'end_time' => '12:00',
        'timezone' => 'UTC',
        'effective_from' => '2026-10-01',
        'effective_to' => '2026-12-31',
        'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
    ]);
    $this->actingAs($fixture['operator']);
    $this->seed(NotificationTemplateSeeder::class);

    Livewire::test(CreateSchedule::class)
        ->fillForm([
            'target_type' => 'student',
            'course_id' => (string) $individualCourse->id,
            'student_profile_id' => (string) $fixture['student']->id,
            'staff_profile_id' => (string) $fixture['teacher']->id,
            'weekdays' => [0],
            'interval_weeks' => 1,
            'duration_minutes' => 35,
            'timezone' => 'UTC',
            'starts_on' => '2026-10-11',
            'ends_on' => '2026-10-11',
            'reason' => 'اختبار إشعار حجز القرآن الفردي',
        ])
        ->set('data.start_time', '10:00')
        ->call('create')
        ->assertHasNoFormErrors();

    $schedule = Schedule::query()->latest('created_at')->firstOrFail();
    $session = Session::query()->where('schedule_id', $schedule->id)->sole();
    $outbox = NotificationOutbox::query()->where('category', 'schedule_summary')->get();
    $emailBody = $outbox->firstWhere('channel', 'email')?->body ?? [];

    expect($outbox)->toHaveCount(4)
        ->and($outbox->pluck('user_id')->unique())->toHaveCount(2)
        ->and(implode(' ', $emailBody))->toContain('القرآن الفردي')
        ->and(implode(' ', $emailBody))->toContain($session->scheduled_start->format('Y-m-d'));
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

it('books an individual student only in approved teacher availability with configured durations', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();
    $fixture['course']->update(['session_mode' => SessionMode::Individual]);
    TeacherAvailability::query()->create([
        'staff_profile_id' => $fixture['teacher']->id,
        'weekday' => 0,
        'start_time' => '09:00',
        'end_time' => '12:00',
        'timezone' => 'UTC',
        'effective_from' => '2026-10-01',
        'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
        'approved_by' => $fixture['operator']->id,
        'approved_at' => now('UTC'),
    ]);

    $payload = schedulePayload($fixture, [
        'target_type' => 'student',
        'group_id' => null,
        'student_profile_id' => (string) $fixture['student']->id,
        'start_time' => '09:00',
        'duration_minutes' => 25,
        'ends_on' => '2026-10-11',
    ]);
    $schedule = app(CreateScheduleAction::class)->execute(
        (string) $fixture['organization']->id,
        $payload,
        (string) $fixture['operator']->id,
        'حجز القرآن الفردي داخل إتاحة المعلم',
    );

    expect($schedule->session_type)->toBe('individual')
        ->and($schedule->duration_minutes)->toBe(25)
        ->and(Session::query()->where('schedule_id', $schedule->id)->count())->toBe(1);

    expect(fn () => app(CreateScheduleAction::class)->execute(
        (string) $fixture['organization']->id,
        [...$payload, 'duration_minutes' => 30],
        (string) $fixture['operator']->id,
        'رفض مدة غير معتمدة للفردي',
    ))->toThrow(BusinessRuleViolation::class);
    expect(fn () => app(CreateScheduleAction::class)->execute(
        (string) $fixture['organization']->id,
        [...$payload, 'start_time' => '08:00', 'duration_minutes' => 35],
        (string) $fixture['operator']->id,
        'رفض موعد خارج إتاحة المعلم',
    ))->toThrow(BusinessRuleViolation::class);
});

it('runs the postponement lifecycle through the Sessions gateway without admin approval', function (): void {
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
        ->and($overflow->requires_admin_review)->toBeFalse();

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

it('bulk places Quran students into distinct slots and activates a missing enrollment', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $this->seed(GeographySeeder::class);
    $fixture = schedulingFixture();
    $countryId = (string) DB::table('countries')->orderBy('id')->value('id');
    $regionId = (string) DB::table('regions')->where('country_id', $countryId)->orderBy('id')->value('id');
    $individualCourse = Course::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'level_id' => $fixture['level']->id,
        'code' => 'C-QURAN-IND',
        'session_mode' => SessionMode::Individual,
        'name' => ['ar' => 'القرآن الفردي', 'en' => 'Individual Quran'],
    ]);
    DB::table('teacher_courses')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $fixture['teacher']->id,
        'course_id' => $individualCourse->id,
        'qualified_at' => now('UTC'),
        'qualified_by' => $fixture['operator']->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);
    TeacherAvailability::query()->create([
        'staff_profile_id' => $fixture['teacher']->id,
        'weekday' => 0,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'timezone' => 'UTC',
        'effective_from' => '2026-10-01',
        'effective_to' => '2026-12-31',
        'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
    ]);
    $secondUser = User::factory()->inOrganization((string) $fixture['organization']->id)->create();
    $secondStudent = StudentProfile::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $secondUser->id,
        'student_code' => 'ST-QURAN-002',
    ]);
    $firstApplication = RegistrationApplication::query()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $fixture['student']->user_id,
        'student_profile_id' => $fixture['student']->id,
        'status' => RegistrationStatus::WaitingAssignment,
        'full_name' => 'طالب القرآن الأول',
        'date_of_birth' => '2010-01-01',
        'gender' => StudentGender::Male,
        'country_id' => $countryId,
        'region_id' => $regionId,
    ]);
    $secondApplication = RegistrationApplication::query()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $secondUser->id,
        'student_profile_id' => $secondStudent->id,
        'status' => RegistrationStatus::WaitingAssignment,
        'full_name' => 'طالب القرآن الثاني',
        'date_of_birth' => '2011-01-01',
        'gender' => StudentGender::Male,
        'country_id' => $countryId,
        'region_id' => $regionId,
    ]);
    $action = app(BulkCreateIndividualQuranSchedulesAction::class);
    $studentIds = [(string) $fixture['student']->id, (string) $secondStudent->id];

    $preview = $action->preview(
        (string) $fixture['organization']->id,
        $studentIds,
        (string) $fixture['teacher']->id,
        [0],
        1,
        35,
        'UTC',
        '2026-10-11',
        '2026-10-11',
        true,
    );
    $result = $action->execute(
        organizationId: (string) $fixture['organization']->id,
        studentProfileIds: $studentIds,
        staffProfileId: (string) $fixture['teacher']->id,
        weekdays: [0],
        intervalWeeks: 1,
        durationMinutes: 35,
        timezone: 'UTC',
        startsOn: '2026-10-11',
        endsOn: '2026-10-11',
        actorId: (string) $fixture['operator']->id,
        reason: 'تسكين جماعي آمن في القرآن الفردي',
        activateEnrollment: true,
    );
    $schedules = Schedule::query()
        ->where('course_id', $individualCourse->id)
        ->orderBy('start_time')
        ->get();

    expect($preview->assignedStartTimes)->toBe(['09:00', '09:35'])
        ->and($result->createdCount())->toBe(2)
        ->and($result->failedCount())->toBe(0)
        ->and($schedules)->toHaveCount(2)
        ->and($schedules->pluck('student_profile_id')->sort()->values()->all())
        ->toBe(collect($studentIds)->sort()->values()->all())
        ->and($schedules->pluck('start_time')->all())->toBe(['09:00:00', '09:35:00'])
        ->and(Session::query()->whereIn('schedule_id', $schedules->pluck('id'))->count())->toBe(2)
        ->and(Enrollment::query()
            ->where('student_profile_id', $secondStudent->id)
            ->where('program_id', $fixture['program']->id)
            ->where('status', EnrollmentStatus::Active)
            ->exists())->toBeTrue()
        ->and($firstApplication->fresh()->status)->toBe(RegistrationStatus::Assigned)
        ->and($secondApplication->fresh()->status)->toBe(RegistrationStatus::Assigned)
        ->and(AuditLog::query()->where('action', 'enrollments.created_by_placement')->exists())->toBeTrue()
        ->and($action->individualQuranStudentIds((string) $fixture['organization']->id))
        ->toEqualCanonicalizing($studentIds)
        ->and($action->activeScheduleIdsByStudent((string) $fixture['organization']->id))
        ->toBe($schedules->pluck('id', 'student_profile_id')->all())
        ->and($action->eligibleStudentIds((string) $fixture['organization']->id))->toBe([]);

    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    $this->actingAs($fixture['operator']);

    $this->get(StudentProfileResource::getUrl('individual-quran', panel: 'admin'))
        ->assertOk()
        ->assertSee(__('students::admin.individual_quran.edit_action'), false)
        ->assertSee(route('filament.admin.resources.schedules.edit', ['record' => $schedules->first()->id]), false)
        ->assertSee('!bg-success-50', false);
});

it('allows the original teacher after substitution and guards invalid duplicate and foreign requests', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
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

    $substituteUser = User::factory()->inOrganization((string) $fixture['organization']->id)->create();
    $substitute = StaffProfile::query()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $substituteUser->id,
        'staff_code' => 'T-SUBSTITUTE',
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Male,
        'hired_at' => '2026-01-01',
    ]);
    $sessions[0]->update(['staff_profile_id' => $substitute->id]);

    $request = app(RequestPostponement::class)->execute(
        (string) $fixture['organization']->id,
        (string) $sessions[0]->id,
        (string) $fixture['teacher']->user_id,
        null,
        $sessions[0]->scheduled_start->addDay(),
        'المعلم الأصلي يطلب التأجيل بعد تكليف بديل',
        (string) $fixture['teacher']->id,
    );

    expect($request->requested_for_student_id)->toBeNull()
        ->and($request->requires_admin_review)->toBeFalse()
        ->and(AuditLog::query()
            ->where('action', 'scheduling.postponement_requested')
            ->where('auditable_id', $request->id)
            ->exists())->toBeTrue();

    expect(fn () => app(RequestPostponement::class)->execute(
        (string) $fixture['organization']->id,
        (string) $sessions[0]->id,
        (string) $fixture['teacher']->user_id,
        null,
        $sessions[0]->scheduled_start->addDays(2),
        'طلب مكرر لنفس الحصة',
        (string) $fixture['teacher']->id,
    ))->toThrow(BusinessRuleViolation::class);

    $sessions[1]->update(['status' => SessionStatus::Completed]);
    expect(fn () => app(RequestPostponement::class)->execute(
        (string) $fixture['organization']->id,
        (string) $sessions[1]->id,
        (string) $fixture['teacher']->user_id,
        null,
        $sessions[1]->scheduled_start->addDays(2),
        'طلب في حالة غير صالحة',
        (string) $fixture['teacher']->id,
    ))->toThrow(BusinessRuleViolation::class);

    $foreignOrganization = Organization::factory()->create();
    expect(fn () => app(RequestPostponement::class)->execute(
        (string) $foreignOrganization->id,
        (string) $sessions[0]->id,
        (string) $fixture['teacher']->user_id,
        null,
        $sessions[0]->scheduled_start->addDays(3),
        'محاولة عابرة للمؤسسات',
        (string) $fixture['teacher']->id,
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

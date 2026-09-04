<?php

declare(strict_types=1);

use App\Http\Controllers\Portal\ClassroomJoinController;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
use Modules\Groups\Domain\Events\StudentLeftGroup;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Modules\Organization\Domain\Models\Organization;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Application\Actions\ConfirmSessionAction;
use Modules\Sessions\Application\Actions\EndSessionAction;
use Modules\Sessions\Application\Actions\StartSessionAction;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Sessions\Presentation\Filament\Resources\SessionParticipantResource;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\StudentProfile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('renders a tenant isolated session operations hub with real names and no join secret', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    $fixture = sessionOperationsFixture();
    $this->actingAs($fixture['operator']);

    $this->get(SessionResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSeeText(__('sessions::actions.dispatch_reminders'));

    $this->get(SessionResource::getUrl('view', ['record' => $fixture['session']], panel: 'admin'))
        ->assertOk()
        ->assertSeeText('جلسة تشغيل حقيقية')
        ->assertSeeText('برنامج التشغيل الحقيقي')
        ->assertSeeText('مقرر التشغيل الحقيقي')
        ->assertSeeText('مجموعة التشغيل الحقيقية')
        ->assertSeeText('المعلم المنفّذ الحقيقي')
        ->assertSeeText('الطالب الحقيقي')
        ->assertSeeText(__('sessions::hub.status_history'))
        ->assertSeeText(__('sessions::hub.audit'))
        ->assertDontSee((string) $fixture['participant']->join_url_token);

    $this->get(SessionResource::getUrl('calendar', panel: 'admin'))
        ->assertOk()
        ->assertSeeText('مجموعة التشغيل الحقيقية')
        ->assertSeeText('المعلم المنفّذ الحقيقي');

    $this->get(SessionParticipantResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSeeText('الطالب الحقيقي')
        ->assertDontSee((string) $fixture['participant']->join_url_token);

    $otherOrganization = Organization::factory()->create();
    $otherOperator = User::factory()->inOrganization((string) $otherOrganization->id)->create();
    $this->actingAs($otherOperator)
        ->get(SessionResource::getUrl('view', ['record' => $fixture['session']], panel: 'admin'))
        ->assertNotFound();
});

it('moves through the session lifecycle only via actions and audits every transition with its actor and reason', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = sessionOperationsFixture();
    Event::fake();
    $session = $fixture['session'];
    $actorId = (string) $fixture['operator']->id;

    $session = app(ConfirmSessionAction::class)->execute($session, $actorId, 'تأكيد الموعد مع المعلم والطلاب');
    $session = app(StartSessionAction::class)->execute($session, $actorId, 'فتح الفصل بعد التحقق من الحضور');
    $session = app(EndSessionAction::class)->execute($session, $actorId, 'إنهاء البث والانتقال لمراجعة الحضور');
    $session = app(CompleteSessionAction::class)->execute($session, $actorId, 'اعتماد الحضور وإقفال الحصة ماليًا');

    expect($session->status)->toBe(SessionStatus::Completed)
        ->and($session->statusHistory()->count())->toBe(4)
        ->and(AuditLog::query()->where('auditable_type', 'sessions')->where('auditable_id', $session->id)->count())->toBe(4)
        ->and(AuditLog::query()->where('actor_id', $actorId)->whereNotNull('reason')->count())->toBeGreaterThanOrEqual(4);
});

it('revokes future invitations when a student leaves a group and blocks the portal and scheduling facts', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = sessionOperationsFixture([
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(10),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(70),
    ]);
    $event = new StudentLeftGroup(
        membershipId: (string) Str::ulid(),
        groupId: (string) $fixture['group']->id,
        organizationId: (string) $fixture['organization']->id,
        studentProfileId: (string) $fixture['student']->id,
        reason: 'نقل الطالب إلى مجموعة أخرى',
        actorId: (string) $fixture['operator']->id,
    );

    event($event);

    $participant = $fixture['participant']->refresh();
    $facts = app(SessionSchedulingQueries::class)->find(
        (string) $fixture['organization']->id,
        (string) $fixture['session']->id,
    );

    expect($participant->revoked_at)->not->toBeNull()
        ->and($participant->revoked_by)->toBe((string) $fixture['operator']->id)
        ->and($participant->revocation_reason)->toBe('نقل الطالب إلى مجموعة أخرى')
        ->and($facts?->studentProfileIds)->toBe([])
        ->and(DB::table('session_participants')
            ->where('session_id', $fixture['session']->id)
            ->whereNull('revoked_at')
            ->exists())->toBeFalse();

    $request = Request::create('/student/sessions/'.$fixture['session']->id.'/join');
    $request->setUserResolver(static fn () => $fixture['studentUser']);
    config()->set('virtual-classroom.default', 'null');

    expect(fn () => app(ClassroomJoinController::class)->student(
        $request,
        (string) $fixture['session']->id,
    ))->toThrow(NotFoundHttpException::class);

    $oldToken = (string) $participant->join_url_token;
    $reactivated = app(SessionSchedulingGateway::class)->addParticipantToFutureGroupSessions(
        (string) $fixture['organization']->id,
        (string) $fixture['group']->id,
        (string) $fixture['course']->id,
        (string) $fixture['student']->id,
        (string) $fixture['enrollment']->id,
    );
    $participant->refresh();

    expect($reactivated)->toBe(1)
        ->and($participant->revoked_at)->toBeNull()
        ->and($participant->join_url_token)->not->toBe($oldToken);
});

it('enforces assigned own children and tenant scopes on session APIs', function (): void {
    $fixture = sessionOperationsFixture();
    $organizationId = (string) $fixture['organization']->id;
    $broad = User::factory()->inOrganization($organizationId)->create();
    $auditor = User::factory()->inOrganization($organizationId)->create();
    $finance = User::factory()->inOrganization($organizationId)->create();
    $unrelated = User::factory()->inOrganization($organizationId)->create();
    $foreignOrganization = Organization::factory()->create();
    $foreign = User::factory()->inOrganization((string) $foreignOrganization->id)->create();

    Gate::define('session.view', static fn (): bool => true);
    Gate::define('student.view.any', static fn (User $user): bool => in_array((string) $user->id, [
        (string) $broad->id,
        (string) $auditor->id,
        (string) $finance->id,
    ], true));
    Gate::define('session.create', static fn (User $user): bool => in_array((string) $user->id, [
        (string) $broad->id,
        (string) $fixture['teacherUser']->id,
    ], true));

    $this->actingAs($fixture['teacherUser'])
        ->getJson('/api/sessions/'.$fixture['session']->id)
        ->assertOk();
    $this->actingAs($fixture['studentUser'])
        ->getJson('/api/sessions/'.$fixture['session']->id)
        ->assertOk();
    $this->actingAs($unrelated)
        ->getJson('/api/sessions/'.$fixture['session']->id)
        ->assertForbidden();
    $this->actingAs($foreign)
        ->getJson('/api/sessions/'.$fixture['session']->id)
        ->assertForbidden();
    $this->actingAs($broad)
        ->getJson('/api/sessions/'.$fixture['session']->id)
        ->assertOk();

    $this->actingAs($unrelated)
        ->getJson('/api/sessions')
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->actingAs($fixture['studentUser'])
        ->getJson('/api/sessions')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($fixture['studentUser'])
        ->postJson('/api/sessions/'.$fixture['session']->id.'/confirm', ['reason' => 'student cannot confirm'])
        ->assertForbidden();
    $this->actingAs($auditor)
        ->postJson('/api/sessions/'.$fixture['session']->id.'/confirm', ['reason' => 'auditor is read only'])
        ->assertForbidden();
    $this->actingAs($finance)
        ->postJson('/api/sessions/'.$fixture['session']->id.'/confirm', ['reason' => 'finance is read only'])
        ->assertForbidden();
    $this->actingAs($fixture['teacherUser'])
        ->postJson('/api/sessions/'.$fixture['session']->id.'/confirm', ['reason' => 'assigned teacher confirmation'])
        ->assertOk();
});

it('allows verified guardian child visibility but requires acting authority for postponement', function (): void {
    $fixture = sessionOperationsFixture();
    $guardianUser = User::factory()
        ->inOrganization((string) $fixture['organization']->id)
        ->create();
    $guardian = GuardianProfile::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $guardianUser->id,
    ]);
    $link = GuardianLink::factory()->verified()->create([
        'guardian_profile_id' => $guardian->id,
        'student_profile_id' => $fixture['student']->id,
        'can_act_for' => false,
        'visible_sections' => ['schedule'],
    ]);

    Gate::define('session.view', static fn (): bool => true);
    Gate::define('session.postpone.request', static fn (): bool => true);
    Gate::define('student.view.any', static fn (): bool => false);

    $this->actingAs($guardianUser)
        ->getJson('/api/sessions/'.$fixture['session']->id)
        ->assertOk();
    expect(Gate::forUser($guardianUser)->denies('postpone', $fixture['session']))->toBeTrue();

    $link->forceFill(['can_act_for' => true])->save();
    expect(Gate::forUser($guardianUser)->allows('postpone', $fixture['session']))->toBeTrue();
});

it('queues one-hour reminders once and respects the student email preference', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => true, 'always_on' => true],
            'email' => ['enabled' => true],
        ],
        'notifications.categories.session_reminder' => [
            'channels' => ['in_app', 'email'],
            'critical' => false,
            'respects_quiet_hours' => false,
        ],
        'scheduling.reminder_dispatch.before_minutes' => 60,
    ]);
    $fixture = sessionOperationsFixture([
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(45),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(80),
    ]);
    $this->seed(NotificationTemplateSeeder::class);
    NotificationPreference::query()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $fixture['studentUser']->id,
        'category' => 'session_reminder',
        'channel' => Channel::Email,
        'enabled' => false,
    ]);

    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();
    $this->artisan('sessions:dispatch-reminders')->assertSuccessful();

    $outbox = NotificationOutbox::query()->where('category', 'session_reminder')->get();
    expect($outbox)->toHaveCount(3)
        ->and($outbox->pluck('event_name')->unique()->all())->toBe(['session.approaching'])
        ->and($outbox->where('user_id', $fixture['studentUser']->id)->pluck('channel')->all())->toBe(['in_app'])
        ->and($outbox->where('user_id', $fixture['teacherUser']->id)->pluck('channel')->sort()->values()->all())
        ->toBe(['email', 'in_app'])
        ->and($fixture['session']->refresh()->reminder_sent_at)->not->toBeNull();
});

it('rolls the participant lifecycle migration down and reapplies it cleanly', function (): void {
    $migration = require base_path('modules/Sessions/database/migrations/2026_08_24_200000_harden_session_participant_lifecycle.php');

    $migration->down();
    expect(Schema::hasColumn('session_participants', 'revoked_at'))->toBeFalse()
        ->and(Schema::hasColumn('session_participants', 'deleted_at'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('session_participants', 'revoked_at'))->toBeTrue()
        ->and(Schema::hasColumn('session_participants', 'deleted_at'))->toBeTrue();
});

/**
 * @param array<string, mixed> $sessionOverrides
 * @return array<string, object>
 */
function sessionOperationsFixture(array $sessionOverrides = []): array
{
    $organization = Organization::factory()->create();
    $operator = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'مشرف التشغيل']);
    $teacherUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'المعلم المنفّذ الحقيقي']);
    $studentUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'الطالب الحقيقي']);
    $program = Program::factory()->create([
        'organization_id' => $organization->id,
        'name' => ['ar' => 'برنامج التشغيل الحقيقي', 'en' => 'Operations Program'],
    ]);
    $level = Level::factory()->create(['program_id' => $program->id]);
    $course = Course::factory()->create([
        'organization_id' => $organization->id,
        'level_id' => $level->id,
        'name' => ['ar' => 'مقرر التشغيل الحقيقي', 'en' => 'Operations Course'],
        'session_mode' => SessionMode::Group,
    ]);
    $teacher = StaffProfile::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $teacherUser->id,
        'staff_code' => 'T-OPS',
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Male,
        'hired_at' => '2026-01-01',
    ]);
    $student = StudentProfile::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $studentUser->id,
        'student_code' => 'ST-OPS',
    ]);
    $enrollment = Enrollment::query()->create([
        'organization_id' => $organization->id,
        'student_profile_id' => $student->id,
        'program_id' => $program->id,
        'current_level_id' => $level->id,
        'status' => EnrollmentStatus::Active,
        'applied_at' => now('UTC')->subMonth(),
        'activated_at' => now('UTC')->subWeek(),
    ]);
    $group = Group::query()->create([
        'organization_id' => $organization->id,
        'code' => 'GR-OPS',
        'name' => ['ar' => 'مجموعة التشغيل الحقيقية', 'en' => 'Operations Group'],
        'capacity' => 10,
        'timezone' => 'Europe/Istanbul',
        'status' => GroupStatus::Active,
        'starts_on' => '2026-01-01',
    ]);
    GroupProgram::query()->create(['group_id' => $group->id, 'program_id' => $program->id]);
    $session = Session::query()->create([
        'organization_id' => $organization->id,
        'group_id' => $group->id,
        'course_id' => $course->id,
        'staff_profile_id' => $teacher->id,
        'original_teacher_id' => $teacher->id,
        'session_type' => 'group',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addDay(),
        'scheduled_end' => CarbonImmutable::now('UTC')->addDay()->addHour(),
        'title' => ['ar' => 'جلسة تشغيل حقيقية', 'en' => 'Real Operations Session'],
        ...$sessionOverrides,
    ]);
    $participant = SessionParticipant::query()->create([
        'session_id' => $session->id,
        'student_profile_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'join_url_token' => Str::random(64),
        'invited_at' => now('UTC'),
        'attended_minutes' => 0,
    ]);

    return compact(
        'organization', 'operator', 'teacherUser', 'studentUser', 'program', 'level',
        'course', 'teacher', 'student', 'enrollment', 'group', 'session', 'participant',
    );
}

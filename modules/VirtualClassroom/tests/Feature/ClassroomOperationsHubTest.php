<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Modules\VirtualClassroom\Application\Actions\CheckClassroomHealthAction;
use Modules\VirtualClassroom\Application\Actions\HandleClassroomWebhookAction;
use Modules\VirtualClassroom\Application\Actions\ProvisionClassroomAction;
use Modules\VirtualClassroom\Domain\Contracts\ClassroomAdministrationQueries;
use Modules\VirtualClassroom\Domain\Contracts\VirtualClassroomProvider;
use Modules\VirtualClassroom\Domain\Enums\ClassroomEventType;
use Modules\VirtualClassroom\Domain\Enums\ClassroomHealthStatus;
use Modules\VirtualClassroom\Domain\Enums\ClassroomStatus;
use Modules\VirtualClassroom\Domain\Exceptions\ClassroomProviderException;
use Modules\VirtualClassroom\Domain\Models\Classroom;
use Modules\VirtualClassroom\Domain\Models\ClassroomEvent;
use Modules\VirtualClassroom\Domain\ValueObjects\ClassroomSpec;
use Modules\VirtualClassroom\Domain\ValueObjects\RemoteClassroom;
use Modules\VirtualClassroom\Domain\ValueObjects\WebhookEvent;
use Modules\VirtualClassroom\Infrastructure\Providers\NullProvider;
use Modules\VirtualClassroom\Presentation\Filament\Pages\ClassroomConnectionSettings;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('provisions checks and renders the classroom inside the session hub without exposing secrets', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    config()->set('virtual-classroom.default', 'null');
    $participantId = $this->createSessionParticipant();
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $operator = User::query()->where('organization_id', $this->organizationId)->firstOrFail();
    app()->instance(VirtualClassroomProvider::class, new NullProvider);

    $classroom = app(ProvisionClassroomAction::class)->execute(
        sessionId: $sessionId,
        title: 'حصة اختبار الفصل المباشر',
        organizationId: $this->organizationId,
        actorId: (string) $operator->id,
        reason: 'تجهيز الفصل قبل موعد الحصة',
    );
    $again = app(ProvisionClassroomAction::class)->execute(
        sessionId: $sessionId,
        title: 'حصة اختبار الفصل المباشر',
        organizationId: $this->organizationId,
        actorId: (string) $operator->id,
        reason: 'تحقق تكراري من جاهزية الفصل',
    );
    $classroom = app(CheckClassroomHealthAction::class)->execute(
        $this->organizationId,
        $sessionId,
        (string) $operator->id,
        'فحص صحة المزوّد قبل بدء الحصة',
    );

    expect($classroom->status)->toBe(ClassroomStatus::Provisioned)
        ->and($classroom->health_status)->toBe(ClassroomHealthStatus::Healthy)
        ->and($classroom->provision_attempts)->toBe(1)
        ->and($again->id)->toBe($classroom->id)
        ->and(app(ClassroomAdministrationQueries::class)
            ->summaryForOrganization($this->organizationId)['provisioned'])->toBe(1);

    /** @var Session $session */
    $session = Session::query()->findOrFail($sessionId);
    $this->actingAs($operator)
        ->get(SessionResource::getUrl('view', ['record' => $session], panel: 'admin'))
        ->assertOk()
        ->assertSeeText(__('sessions::hub.classroom'))
        ->assertSeeText(__('virtualclassroom::status.provisioned'))
        ->assertSeeText(__('virtualclassroom::health.healthy'))
        ->assertDontSee((string) $classroom->moderator_secret)
        ->assertDontSee((string) $classroom->attendee_secret);

    $this->get(ClassroomConnectionSettings::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSeeText(__('virtualclassroom::settings.metric_provisioned'));

    expect(AuditLog::query()
        ->where('auditable_type', 'classrooms')
        ->where('auditable_id', $classroom->id)
        ->count())->toBe(2);
});

it('persists a failed provisioning attempt and retries the same classroom safely', function (): void {
    $participantId = $this->createSessionParticipant();
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $operatorId = (string) User::query()->where('organization_id', $this->organizationId)->value('id');
    $failingProvider = Mockery::mock(VirtualClassroomProvider::class);
    $failingProvider->shouldReceive('name')->andReturn('unavailable-provider');
    $failingProvider->shouldReceive('createClassroom')
        ->once()
        ->andThrow(ClassroomProviderException::unavailable());
    app()->instance(VirtualClassroomProvider::class, $failingProvider);

    expect(fn () => app(ProvisionClassroomAction::class)->execute(
        $sessionId,
        'فصل سيفشل مؤقتًا',
        organizationId: $this->organizationId,
        actorId: $operatorId,
        reason: 'محاولة إنشاء أولى قبل الموعد',
    ))->toThrow(ClassroomProviderException::class);

    $failed = Classroom::query()->forSession($sessionId)->firstOrFail();
    expect($failed->status)->toBe(ClassroomStatus::Failed)
        ->and($failed->provision_attempts)->toBe(1)
        ->and($failed->last_error)->not->toBeNull()
        ->and($failed->external_id)->toBeNull();

    app()->instance(VirtualClassroomProvider::class, new NullProvider);
    $retried = app(ProvisionClassroomAction::class)->execute(
        $sessionId,
        'الفصل بعد عودة الخدمة',
        organizationId: $this->organizationId,
        actorId: $operatorId,
        reason: 'إعادة المحاولة بعد عودة المزوّد',
    );

    expect($retried->id)->toBe($failed->id)
        ->and($retried->status)->toBe(ClassroomStatus::Provisioned)
        ->and($retried->provision_attempts)->toBe(2)
        ->and($retried->last_error)->toBeNull()
        ->and(AuditLog::query()->where('auditable_id', $retried->id)->pluck('action')->all())
        ->toEqualCanonicalizing([
            'virtualclassroom.provision_failed',
            'virtualclassroom.provisioned',
        ]);
});

it('reprovisions a remote classroom that ended before a portal join', function (): void {
    $fixture = new class
    {
        use CreatesSessionParticipant {
            createSessionParticipant as public;
        }
    };
    $participantId = $fixture->createSessionParticipant();
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $organizationId = (string) DB::table('sessions')->where('id', $sessionId)->value('organization_id');
    $operatorId = (string) User::query()->where('organization_id', $organizationId)->value('id');
    $provider = Mockery::mock(VirtualClassroomProvider::class);
    $provider->shouldReceive('name')->andReturn('bigbluebutton');
    $provider->shouldReceive('createClassroom')
        ->twice()
        ->andReturnUsing(static fn (ClassroomSpec $spec): RemoteClassroom => new RemoteClassroom(
            externalId: $spec->externalMeetingId,
            moderatorSecret: 'moderator-'.$spec->externalMeetingId,
            attendeeSecret: 'attendee-'.$spec->externalMeetingId,
            createdAt: CarbonImmutable::now('UTC'),
        ));
    $provider->shouldReceive('isRunning')
        ->once()
        ->with('SES-'.$sessionId)
        ->andReturnFalse();
    app()->instance(VirtualClassroomProvider::class, $provider);

    $first = app(ProvisionClassroomAction::class)->execute(
        $sessionId,
        'فصل سينتهي عند المزوّد',
        organizationId: $organizationId,
        actorId: $operatorId,
        reason: 'تجهيز الفصل قبل دخول المعلم',
    );
    $recovered = app(ProvisionClassroomAction::class)->execute(
        $sessionId,
        'فصل بديل بعد انتهاء الغرفة الأولى',
        organizationId: $organizationId,
        actorId: $operatorId,
        reason: 'إعادة التجهيز عند طلب دخول المعلم',
        ensureRemoteIsRunning: true,
    );

    expect($first->external_id)->toBe('SES-'.$sessionId)
        ->and($recovered->id)->toBe($first->id)
        ->and($recovered->external_id)->toBe('SES-'.$sessionId.'-R2')
        ->and($recovered->status)->toBe(ClassroomStatus::Provisioned)
        ->and($recovered->provision_attempts)->toBe(2)
        ->and(AuditLog::query()
            ->where('auditable_id', $recovered->id)
            ->where('action', 'virtualclassroom.remote_classroom_unavailable')
            ->exists())->toBeTrue();
});

it('maps webhook participant identities and lifecycle into local events and audit', function (): void {
    $participantId = $this->createSessionParticipant();
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $studentUserId = (string) DB::table('student_profiles')
        ->where('id', DB::table('session_participants')->where('id', $participantId)->value('student_profile_id'))
        ->value('user_id');
    app()->instance(VirtualClassroomProvider::class, new NullProvider);
    $classroom = app(ProvisionClassroomAction::class)->execute(
        $sessionId,
        'فصل webhook',
        organizationId: $this->organizationId,
        reason: 'تجهيز الفصل للاختبار',
    );
    $startedAt = CarbonImmutable::parse('2026-09-10 09:00:00 UTC');
    $provider = Mockery::mock(VirtualClassroomProvider::class);
    $provider->shouldReceive('name')->andReturn('null');
    $provider->shouldReceive('parseWebhook')->twice()->andReturn(
        new WebhookEvent(
            ClassroomEventType::MeetingStarted,
            (string) $classroom->external_id,
            null,
            $startedAt,
            ['data' => ['id' => 'meeting-started-1']],
        ),
        new WebhookEvent(
            ClassroomEventType::ParticipantJoined,
            (string) $classroom->external_id,
            $studentUserId,
            $startedAt->addMinute(),
            ['data' => ['id' => 'user-joined-1', 'attributes' => ['user' => ['role' => 'VIEWER']]]],
        ),
    );
    app()->instance(VirtualClassroomProvider::class, $provider);
    $action = app(HandleClassroomWebhookAction::class);

    $action->execute(Request::create('/webhook', 'POST'));
    $event = $action->execute(Request::create('/webhook', 'POST'));

    expect($classroom->refresh()->status)->toBe(ClassroomStatus::Running)
        ->and($event?->user_id)->toBe($studentUserId)
        ->and(ClassroomEvent::query()->where('user_id', $studentUserId)->exists())->toBeTrue()
        ->and(AuditLog::query()
            ->where('auditable_id', $classroom->id)
            ->where('action', 'virtualclassroom.running')
            ->exists())->toBeTrue();
});

it('rolls the classroom operations migration down and reapplies it cleanly', function (): void {
    $migration = require base_path('modules/VirtualClassroom/database/migrations/2026_08_24_220000_harden_classroom_operations.php');

    $migration->down();
    expect(Schema::hasColumn('classrooms', 'status'))->toBeFalse()
        ->and(Schema::hasColumn('classrooms', 'deleted_at'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('classrooms', 'status'))->toBeTrue()
        ->and(Schema::hasColumn('classrooms', 'deleted_at'))->toBeTrue();
});

it('provisions upcoming classrooms automatically within the configured lead window', function (): void {
    CarbonImmutable::setTestNow('2026-09-10 08:00:00 UTC');
    config()->set('virtual-classroom.default', 'null');
    config()->set('virtual-classroom.provisioning.before_minutes', 20);
    $participantId = $this->createSessionParticipant();
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    Session::query()->whereKey($sessionId)->update([
        'status' => 'scheduled',
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(15),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(75),
    ]);
    app()->instance(VirtualClassroomProvider::class, new NullProvider);

    $this->artisan('classroom:provision-upcoming')
        ->expectsOutput(__('virtualclassroom::messages.provisioning_summary', [
            'provisioned' => 1,
            'failed' => 0,
        ]))
        ->assertSuccessful();

    expect(Classroom::query()->forSession($sessionId)->sole()->status)
        ->toBe(ClassroomStatus::Provisioned);
});

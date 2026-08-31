<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Actions\CancelNotificationAction;
use Modules\Notifications\Application\Actions\RetryNotificationAction;
use Modules\Notifications\Domain\Contracts\NotificationAdministrationQueries;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

it('returns localized portal notifications with a safe deep link and accurate unread count', function (): void {
    $participantId = $this->createSessionParticipant();
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $studentUserId = (string) DB::table('student_profiles')
        ->where('id', DB::table('session_participants')->where('id', $participantId)->value('student_profile_id'))
        ->value('user_id');
    $student = User::query()->findOrFail($studentUserId);
    $student->forceFill(['locale' => 'ar'])->save();
    NotificationOutbox::factory()->withChannel(Channel::InApp)->sent()->create([
        'organization_id' => $this->organizationId,
        'user_id' => $studentUserId,
        'event_name' => 'session.scheduled',
        'subject' => ['ar' => 'موعد الحصة', 'en' => 'Session time'],
        'body' => ['ar' => 'الحصة جاهزة للعرض', 'en' => 'The session is ready'],
        'payload' => ['session_id' => $sessionId],
    ]);

    Gate::define('session.view', static fn (): bool => true);
    $this->actingAs($student)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.subject', 'موعد الحصة')
        ->assertJsonPath('data.0.body', 'الحصة جاهزة للعرض')
        ->assertJsonPath('data.0.target_url', "/student/sessions/{$sessionId}");

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);
});

it('renders real recipients and session delivery history in both operations hubs', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    $participantId = $this->createSessionParticipant();
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $studentUserId = (string) DB::table('student_profiles')
        ->where('id', DB::table('session_participants')->where('id', $participantId)->value('student_profile_id'))
        ->value('user_id');
    $operator = User::query()->where('organization_id', $this->organizationId)->firstOrFail();
    $outbox = NotificationOutbox::factory()->withChannel(Channel::InApp)->sent()->create([
        'organization_id' => $this->organizationId,
        'user_id' => $studentUserId,
        'event_name' => 'session.scheduled',
        'payload' => ['session_id' => $sessionId],
    ]);

    $delivery = app(NotificationAdministrationQueries::class)
        ->forSession($this->organizationId, $sessionId)[0] ?? null;
    expect($delivery)->not->toBeNull()
        ->and($delivery?->status)->toBe(OutboxStatus::Sent->value);

    $this->actingAs($operator)
        ->get(NotificationOutboxResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSeeText('طالب تجريبي');

    $this->get(NotificationOutboxResource::getUrl('view', ['record' => $outbox], panel: 'admin'))
        ->assertOk()
        ->assertSeeText('طالب تجريبي')
        ->assertSeeText(__('notifications::fields.attempts_history'));

    /** @var Session $session */
    $session = Session::query()->findOrFail($sessionId);
    $this->get(SessionResource::getUrl('view', ['record' => $session], panel: 'admin'))
        ->assertOk()
        ->assertSeeText(__('sessions::hub.notifications'))
        ->assertSeeText('طالب تجريبي')
        ->assertSeeText(OutboxStatus::Sent->label());
});

it('requires reasons and audits manual retry and cancellation decisions', function (): void {
    $participantId = $this->createSessionParticipant();
    $operator = User::query()->where('organization_id', $this->organizationId)->firstOrFail();
    $failed = NotificationOutbox::factory()->withChannel(Channel::InApp)->failed()->create([
        'organization_id' => $this->organizationId,
        'user_id' => (string) $operator->id,
    ]);
    expect(fn () => app(RetryNotificationAction::class)->executeManually(
        $failed,
        (string) $operator->id,
        ' ',
    ))->toThrow(BusinessRuleViolation::class);
    app(RetryNotificationAction::class)->executeManually(
        $failed,
        (string) $operator->id,
        'أُصلح إعداد القناة وأعيد الإرسال',
    );

    $queued = NotificationOutbox::factory()->withChannel(Channel::InApp)->create([
        'organization_id' => $this->organizationId,
        'user_id' => (string) $operator->id,
    ]);
    expect(fn () => app(CancelNotificationAction::class)->execute(
        $queued,
        ' ',
        (string) $operator->id,
    ))->toThrow(BusinessRuleViolation::class);
    app(CancelNotificationAction::class)->execute(
        $queued,
        'لم تعد الرسالة مطلوبة بعد تغيير الموعد',
        (string) $operator->id,
    );

    expect(AuditLog::query()
        ->where('organization_id', $this->organizationId)
        ->where('auditable_type', 'notification_outbox')
        ->pluck('action')->all())
        ->toContain('notifications.manual_retry', 'notifications.cancelled');
});

it('protects operational notification endpoints with authentication', function (): void {
    $queued = NotificationOutbox::factory()->withChannel(Channel::InApp)->create();
    $failed = NotificationOutbox::factory()->withChannel(Channel::InApp)->failed()->create();

    $this->postJson("/api/notifications/{$queued->id}/cancel", ['reason' => 'اختبار الحماية'])
        ->assertUnauthorized();
    $this->postJson("/api/notifications/{$failed->id}/retry", ['reason' => 'اختبار الحماية'])
        ->assertUnauthorized();
    $this->getJson("/api/notifications/{$queued->id}/attempts")
        ->assertUnauthorized();
});

it('accepts the real browser session on notification api routes', function (): void {
    $route = app('router')->getRoutes()->getByName('notifications.index');
    expect($route)->not->toBeNull()
        ->and(app('router')->gatherRouteMiddleware($route))->toContain(EnsureFrontendRequestsAreStateful::class);

    $this->createSessionParticipant();
    $user = User::factory()->create([
        'organization_id' => $this->organizationId,
        'email' => 'notification.session@example.test',
        'password' => Hash::make('browser-password'),
        'status' => 'active',
    ]);

    $this->post('/login', [
        'login' => 'notification.session@example.test',
        'password' => 'browser-password',
    ])->assertRedirect();
    $this->assertAuthenticatedAs($user);
    Auth::forgetGuards();

    $this->getJson('/api/notifications')->assertOk();
    $this->getJson('/api/notifications/unread-count')->assertOk();
});

it('provides the operations hub labels in every supported admin locale', function (): void {
    foreach (['ar', 'en', 'fr'] as $locale) {
        foreach ([
            'notifications::fields.recipient',
            'notifications::fields.audit_history',
            'notifications::actions.manual_retry',
            'notifications::actions.cancel',
            'notifications::categories.session_changed',
            'sessions::hub.notifications',
            'sessions::fields.notification_status',
        ] as $key) {
            expect(Lang::has($key, $locale))->toBeTrue("Missing {$key} for {$locale}");
        }
    }
});

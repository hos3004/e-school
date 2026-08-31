<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Modules\Audit\Domain\Models\AuditLog;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Recordings\Application\Actions\GrantRecordingAccessAction;
use Modules\Recordings\Application\Actions\LogRecordingViewAction;
use Modules\Recordings\Application\Actions\MarkRecordingReadyAction;
use Modules\Recordings\Application\Actions\RevokeRecordingAccessAction;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Domain\Models\RecordingView;
use Modules\Recordings\Presentation\Filament\Resources\RecordingResource;
use Modules\Recordings\Tests\Concerns\CreatesRecordingContext;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class, CreatesRecordingContext::class);

beforeEach(function (): void {
    $this->context = $this->createSessionWithClassroom();
});

it('renders a tenant isolated recording operations hub and embeds it in the session hub', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    $operator = User::query()->where('organization_id', $this->organizationId)->firstOrFail();
    $viewer = User::factory()->inOrganization($this->organizationId)->create(['name' => 'مشاهد التسجيل']);
    $recording = Recording::factory()->withStatus(RecordingStatus::Processing)->create($this->context);
    $recording = app(MarkRecordingReadyAction::class)->execute(
        $recording,
        durationSeconds: 3600,
        sizeBytes: 200_000_000,
        actorId: (string) $operator->id,
        reason: 'اكتملت معالجة الملف لدى المزوّد',
    );
    $grant = app(GrantRecordingAccessAction::class)->execute(
        $recording,
        (string) $operator->id,
        grantedToUserId: (string) $viewer->id,
        expiresAt: CarbonImmutable::instance($recording->expires_at)->addDays(10),
        reason: 'إتاحة مراجعة الحصة للطالب',
    );
    app(LogRecordingViewAction::class)->execute(
        $recording,
        (string) $viewer->id,
        ipAddress: '127.0.0.1',
    );

    expect($grant->expires_at->equalTo($recording->expires_at))->toBeTrue();
    $administration = app(RecordingAdministrationQueries::class)
        ->findForOrganization($this->organizationId, (string) $recording->id);
    expect($administration)->not->toBeNull()
        ->and($administration?->activeGrantCount)->toBe(1)
        ->and($administration?->viewCount)->toBe(1);

    $this->actingAs($operator)
        ->get(RecordingResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSeeText('حصة اختبار')
        ->assertSeeText('مادة اختبار')
        ->assertDontSee((string) $recording->session_id);

    $this->get(RecordingResource::getUrl('view', ['record' => $recording], panel: 'admin'))
        ->assertOk()
        ->assertSeeText('مشاهد التسجيل')
        ->assertSeeText('إتاحة مراجعة الحصة للطالب')
        ->assertSeeText(__('recordings::hub.audit'))
        ->assertDontSee((string) $recording->path);

    /** @var Session $session */
    $session = Session::query()->findOrFail($this->context['session_id']);
    $this->get(SessionResource::getUrl('view', ['record' => $session], panel: 'admin'))
        ->assertOk()
        ->assertSeeText(__('sessions::hub.recordings'))
        ->assertSeeText(RecordingStatus::Ready->label())
        ->assertSeeText('1');

    $otherOrganization = Organization::factory()->create();
    $otherOperator = User::factory()->inOrganization((string) $otherOrganization->id)->create();
    $this->actingAs($otherOperator);
    expect(RecordingResource::getEloquentQuery()->whereKey($recording->id)->exists())->toBeFalse();
});

it('validates and audits access grants and revocation', function (): void {
    $operator = User::query()->where('organization_id', $this->organizationId)->firstOrFail();
    $viewer = User::factory()->inOrganization($this->organizationId)->create();
    $recording = Recording::factory()->ready()->create($this->context);
    $grant = app(GrantRecordingAccessAction::class)->execute(
        $recording,
        (string) $operator->id,
        grantedToUserId: (string) $viewer->id,
        reason: 'احتياج أكاديمي موثق',
    );
    app(RevokeRecordingAccessAction::class)->execute(
        $recording,
        (string) $grant->id,
        (string) $operator->id,
        'انتهاء الاحتياج الأكاديمي',
    );

    expect($grant->refresh()->revoked_at)->not->toBeNull()
        ->and(AuditLog::query()
            ->where('auditable_type', 'recordings')
            ->where('auditable_id', $recording->id)
            ->pluck('action')->all())
        ->toContain('recordings.access_granted', 'recordings.access_revoked');

    $otherOrganization = Organization::factory()->create();
    $foreignViewer = User::factory()->inOrganization((string) $otherOrganization->id)->create();
    expect(fn () => app(GrantRecordingAccessAction::class)->execute(
        $recording,
        (string) $operator->id,
        grantedToUserId: (string) $foreignViewer->id,
        reason: 'محاولة عابرة للمؤسسات',
    ))->toThrow(BusinessRuleViolation::class);
});

it('enforces retention from the scheduled command and completes archived records', function (): void {
    config()->set('recordings.on_expiry', 'archive_then_delete');
    config()->set('recordings.storage.archive_driver', 'google_drive');
    $recording = Recording::factory()->pastRetention()->create($this->context);

    $this->artisan('recordings:enforce-retention')->assertSuccessful();
    expect($recording->refresh()->status)->toBe(RecordingStatus::Archived);

    $this->artisan('recordings:enforce-retention')->assertSuccessful();
    expect($recording->refresh()->status)->toBe(RecordingStatus::Expired);
});

it('requires a temporary signed URL and logs successful playback', function (): void {
    Gate::before(static fn (): bool => true);
    $operator = User::query()->where('organization_id', $this->organizationId)->firstOrFail();
    $recording = Recording::factory()->ready()->create([
        ...$this->context,
        'path' => 'https://recordings.example.test/session.mp4',
    ]);

    $this->actingAs($operator)
        ->get(route('portal.recordings.watch', ['recording' => $recording]))
        ->assertForbidden();

    $signedUrl = URL::temporarySignedRoute(
        'portal.recordings.watch',
        now()->addMinutes(5),
        ['recording' => $recording],
    );
    $this->get($signedUrl)
        ->assertRedirect('https://recordings.example.test/session.mp4');

    expect(RecordingView::query()
        ->where('recording_id', $recording->id)
        ->where('user_id', $operator->id)
        ->count())->toBe(1);
});

<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Application\Services\CategorySettingsSynchronizer;
use Modules\Notifications\Application\Services\NotificationCategorySettingsResolver;
use Modules\Notifications\Domain\Contracts\NotificationDispatcher;
use Modules\Notifications\Domain\Models\NotificationCategorySetting;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Filament\Resources\NotificationCategorySettingResource;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

function categorySettingRecipient(): string
{
    $userId = Fixtures::userId();

    DB::table('users')->where('id', $userId)->update([
        'email' => 'routing@example.test',
        'phone' => '01001234567',
        'phone_country' => 'EG',
        'locale' => 'ar',
        'timezone' => 'UTC',
    ]);

    return $userId;
}

beforeEach(function (): void {
    /** @var \Tests\TestCase $this */
    config([
        'notifications.channels' => [
            'in_app' => ['enabled' => true],
            'email' => ['enabled' => true],
            'whatsapp' => ['enabled' => true],
        ],
        'notifications.categories.session_changed' => [
            'channels' => ['in_app', 'whatsapp', 'email'],
            'critical' => true,
        ],
        'notifications.quiet_hours.enabled' => false,
    ]);
});

it('falls back to the configuration when the organization has no override', function (): void {
    /** @var \Tests\TestCase $this */
    $resolver = new NotificationCategorySettingsResolver;
    $orgId = (string) Str::ulid();

    expect($resolver->channels($orgId, 'session_changed'))
        ->toBe(['in_app', 'whatsapp', 'email'])
        ->and($resolver->isCritical($orgId, 'session_changed'))->toBeTrue()
        ->and($resolver->respectsQuietHours($orgId, 'session_changed'))->toBeTrue();
});

it('returns the organization override instead of the configuration when present', function (): void {
    /** @var \Tests\TestCase $this */
    $orgId = Fixtures::organizationId();

    NotificationCategorySetting::query()->create([
        'organization_id' => $orgId,
        'category' => 'session_changed',
        'channels' => ['email'],
        'is_critical' => false,
        'respects_quiet_hours' => false,
    ]);

    $resolver = new NotificationCategorySettingsResolver;

    expect($resolver->channels($orgId, 'session_changed'))->toBe(['email'])
        ->and($resolver->isCritical($orgId, 'session_changed'))->toBeFalse()
        ->and($resolver->respectsQuietHours($orgId, 'session_changed'))->toBeFalse();
});

it('routes an event to only the channels the organization configured', function (): void {
    /** @var \Tests\TestCase $this */
    $userId = categorySettingRecipient();

    NotificationCategorySetting::query()->create([
        'organization_id' => Fixtures::organizationId(),
        'category' => 'session_changed',
        'channels' => ['email'],
        'is_critical' => true,
        'respects_quiet_hours' => true,
    ]);

    app(NotificationDispatcher::class)->dispatch(
        category: 'session_changed',
        recipientIds: [$userId],
        payload: [
            'event_name' => 'session.scheduled',
            'event_id' => (string) Str::ulid(),
            'scheduled_start' => '2026-08-23T10:00:00Z',
        ],
    );

    $channels = NotificationOutbox::query()->pluck('channel')
        ->map(static fn (mixed $channel): string => (string) $channel)
        ->all();

    expect($channels)->toContain('email')
        ->and($channels)->toContain('in_app')
        ->and($channels)->not->toContain('whatsapp');
});

it('keeps the configured channels when no organization override exists', function (): void {
    /** @var \Tests\TestCase $this */
    $userId = categorySettingRecipient();

    app(NotificationDispatcher::class)->dispatch(
        category: 'session_changed',
        recipientIds: [$userId],
        payload: [
            'event_name' => 'session.scheduled',
            'event_id' => (string) Str::ulid(),
            'scheduled_start' => '2026-08-23T10:00:00Z',
        ],
    );

    $channels = NotificationOutbox::query()->pluck('channel')
        ->map(static fn (mixed $channel): string => (string) $channel)
        ->all();

    expect($channels)->toContain('whatsapp')
        ->and($channels)->toContain('email')
        ->and($channels)->toContain('in_app');
});

it('synchronizes a row for every configured category without overwriting customizations', function (): void {
    /** @var \Tests\TestCase $this */
    $orgId = Fixtures::organizationId();
    $configuredCount = count((array) config('notifications.categories'));

    $custom = NotificationCategorySetting::query()->create([
        'organization_id' => $orgId,
        'category' => 'session_changed',
        'channels' => ['email'],
        'is_critical' => false,
        'respects_quiet_hours' => false,
    ]);

    $synchronizer = new CategorySettingsSynchronizer;
    $synchronizer->ensureForOrganization($orgId);
    $synchronizer->ensureForOrganization($orgId); // idempotent

    expect(NotificationCategorySetting::query()->forOrganization($orgId)->count())
        ->toBe($configuredCount)
        ->and($custom->fresh()->channels)->toBe(['email'])
        ->and($custom->fresh()->is_critical)->toBeFalse();
});

it('scopes the settings resource to the users organization and opens the edit page', function (): void {
    /** @var \Tests\TestCase $this */
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');

    $mineOrg = Fixtures::organizationId();
    $otherOrg = (string) Str::ulid();
    DB::table('organizations')->insert([
        'id' => $otherOrg,
        'name' => json_encode(['ar' => 'أخرى', 'en' => 'Other'], JSON_THROW_ON_ERROR),
        'slug' => 'other-'.strtolower(substr($otherOrg, -8)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $mine = NotificationCategorySetting::query()->create([
        'organization_id' => $mineOrg,
        'category' => 'session_changed',
        'channels' => ['email'],
        'is_critical' => true,
        'respects_quiet_hours' => true,
    ]);
    $other = NotificationCategorySetting::query()->create([
        'organization_id' => $otherOrg,
        'category' => 'session_changed',
        'channels' => ['email'],
        'is_critical' => true,
        'respects_quiet_hours' => true,
    ]);

    $user = User::factory()->inOrganization($mineOrg)->create();
    $this->actingAs($user);

    $ids = NotificationCategorySettingResource::getEloquentQuery()->pluck('id')->all();
    expect($ids)->toContain($mine->id)->and($ids)->not->toContain($other->id);

    $this->get(NotificationCategorySettingResource::getUrl('edit', ['record' => $mine], panel: 'admin'))
        ->assertOk();
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Notifications\Application\Actions\UpdatePreferenceAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Events\NotificationPreferencesUpdated;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Testing\Fixtures;

it('creates a preference on first update and reuses the same row afterwards', function (): void {
    Event::fake([NotificationPreferencesUpdated::class]);

    $action = app(UpdatePreferenceAction::class);
    $userId = Fixtures::userId();
    $organizationId = Fixtures::organizationId();

    $created = $action->execute($organizationId, $userId, 'scheduling', Channel::Email, enabled: false);

    expect($created->enabled)->toBeFalse()
        ->and(NotificationPreference::query()->count())->toBe(1);

    $updated = $action->execute($organizationId, $userId, 'scheduling', Channel::Email, enabled: true);

    expect($updated->id)->toBe($created->id)
        ->and($updated->refresh()->enabled)->toBeTrue()
        ->and(NotificationPreference::query()->count())->toBe(1);

    Event::assertDispatchedTimes(NotificationPreferencesUpdated::class, 2);
});

it('keeps preferences for different channels independent', function (): void {
    $action = app(UpdatePreferenceAction::class);
    $userId = Fixtures::userId();
    $organizationId = Fixtures::organizationId();

    $emailOff = $action->execute($organizationId, $userId, 'billing', Channel::Email, enabled: false);
    $inAppOn = $action->execute($organizationId, $userId, 'billing', Channel::InApp, enabled: true);

    expect($inAppOn->id)->not->toBe($emailOff->id)
        ->and(NotificationPreference::query()->where('user_id', $userId)->count())->toBe(2)
        ->and(NotificationPreference::query()->where('user_id', $userId)->enabled()->count())->toBe(1);
});

it('scopes queries to the organization they belong to', function (): void {
    NotificationPreference::factory()->count(3)->create();

    $scoped = NotificationPreference::query()
        ->forOrganization(Fixtures::organizationId())
        ->get();

    expect($scoped->count())->toBe(3);
});

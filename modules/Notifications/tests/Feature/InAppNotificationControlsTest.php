<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Tests\Support\ApiUser;
use Shared\Testing\Fixtures;
use Tests\TestCase;

function notificationControlsOrganization(): string
{
    $id = (string) Str::ulid();

    DB::table('organizations')->insert([
        'id' => $id,
        'name' => json_encode(['ar' => 'مؤسسة عزل', 'en' => 'Isolation Organization'], JSON_UNESCAPED_UNICODE),
        'slug' => 'notification-isolation-'.strtolower(substr($id, -8)),
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);

    return $id;
}

it('protects the in-app notification endpoints with authentication', function (): void {
    /** @var TestCase $this */
    $this->getJson('/api/notifications')->assertUnauthorized();
    $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/notifications/mark-all-as-read')->assertUnauthorized();
});

it('lists and counts only delivered in-app notifications owned by the current tenant user', function (): void {
    /** @var TestCase $this */
    $organizationId = Fixtures::organizationId();
    $ownerId = Fixtures::userId();
    $otherUserId = Fixtures::userId();
    $foreignOrganizationId = notificationControlsOrganization();

    $visible = NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->sent()
        ->create(['organization_id' => $organizationId, 'user_id' => $ownerId]);

    NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->sent()
        ->create(['organization_id' => $organizationId, 'user_id' => $otherUserId]);
    NotificationOutbox::factory()
        ->withChannel(Channel::Email)
        ->sent()
        ->create(['organization_id' => $organizationId, 'user_id' => $ownerId]);
    NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->create(['organization_id' => $organizationId, 'user_id' => $ownerId]);

    // صف غير متسق عمدًا يثبت أن user_id وحده لا يكفي لعبور المؤسسة.
    NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->sent()
        ->create(['organization_id' => $foreignOrganizationId, 'user_id' => $ownerId]);

    $actor = new ApiUser($ownerId, $organizationId);

    $this->actingAs($actor)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.is_read', false);

    $this->actingAs($actor)
        ->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);
});

it('marks one notification idempotently and hides records owned by another user', function (): void {
    /** @var TestCase $this */
    $organizationId = Fixtures::organizationId();
    $ownerId = Fixtures::userId();
    $otherUserId = Fixtures::userId();
    $actor = new ApiUser($ownerId, $organizationId);

    $owned = NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->sent()
        ->create(['organization_id' => $organizationId, 'user_id' => $ownerId]);
    $other = NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->sent()
        ->create(['organization_id' => $organizationId, 'user_id' => $otherUserId]);

    $this->actingAs($actor)
        ->postJson("/api/notifications/{$owned->id}/mark-as-read")
        ->assertOk()
        ->assertJsonPath('data.is_read', true);

    $firstReadAt = $owned->refresh()->read_at;

    $this->actingAs($actor)
        ->postJson("/api/notifications/{$owned->id}/mark-as-read")
        ->assertOk();

    expect($owned->refresh()->read_at?->equalTo($firstReadAt))->toBeTrue();

    $this->actingAs($actor)
        ->postJson("/api/notifications/{$other->id}/mark-as-read")
        ->assertNotFound();

    expect($other->refresh()->read_at)->toBeNull();
});

it('marks all and updates only unread delivered in-app records in the current user organization', function (): void {
    /** @var TestCase $this */
    $organizationId = Fixtures::organizationId();
    $ownerId = Fixtures::userId();
    $otherUserId = Fixtures::userId();
    $foreignOrganizationId = notificationControlsOrganization();

    $ownedUnread = NotificationOutbox::factory()
        ->count(2)
        ->withChannel(Channel::InApp)
        ->sent()
        ->create(['organization_id' => $organizationId, 'user_id' => $ownerId]);
    $otherUser = NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->sent()
        ->create(['organization_id' => $organizationId, 'user_id' => $otherUserId]);
    $otherChannel = NotificationOutbox::factory()
        ->withChannel(Channel::Email)
        ->sent()
        ->create(['organization_id' => $organizationId, 'user_id' => $ownerId]);
    $notDelivered = NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->create(['organization_id' => $organizationId, 'user_id' => $ownerId]);
    $foreignTenant = NotificationOutbox::factory()
        ->withChannel(Channel::InApp)
        ->sent()
        ->create(['organization_id' => $foreignOrganizationId, 'user_id' => $ownerId]);

    $this->actingAs(new ApiUser($ownerId, $organizationId))
        ->postJson('/api/notifications/mark-all-as-read')
        ->assertOk()
        ->assertJsonPath('data.marked_count', 2);

    foreach ($ownedUnread as $notification) {
        expect($notification->refresh()->read_at)->not->toBeNull();
    }

    expect($otherUser->refresh()->read_at)->toBeNull()
        ->and($otherChannel->refresh()->read_at)->toBeNull()
        ->and($notDelivered->refresh()->read_at)->toBeNull()
        ->and($foreignTenant->refresh()->read_at)->toBeNull();
});

<?php

declare(strict_types=1);

use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Modules\Identity\Tests\Support\IdentityPestContext;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    /** @var IdentityPestContext $this */
    $this->createTestOrganization();
});

it('registers a device for the authenticated user over HTTP', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    $this->actingAs($user)
        ->postJson('/api/identity/devices', [
            'device_name' => 'Pixel 8',
            'platform' => 'android',
            'push_token' => str_repeat('e', 64),
        ])
        ->assertCreated()
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonPath('data.revoked', false);

    expect(UserDevice::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('lets the owner revoke their device over HTTP', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    /** @var UserDevice $device */
    $device = UserDevice::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->deleteJson("/api/identity/devices/{$device->id}")
        ->assertOk()
        ->assertJsonPath('data.revoked', true);

    expect($device->fresh()->isRevoked())->toBeTrue();
});

it('forbids revoking a device owned by someone else', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $owner */
    $owner = User::factory()->inOrganization($this->organizationId)->create();
    /** @var User $intruder */
    $intruder = User::factory()->inOrganization($this->organizationId)->create();

    /** @var UserDevice $device */
    $device = UserDevice::factory()->forUser($owner)->create();

    $this->actingAs($intruder)
        ->deleteJson("/api/identity/devices/{$device->id}")
        ->assertForbidden();
});

it('requires authentication to register a device', function (): void {
    /** @var IdentityPestContext $this */
    $this->postJson('/api/identity/devices', [])->assertUnauthorized();
});

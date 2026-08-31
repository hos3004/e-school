<?php

declare(strict_types=1);

use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $this->createTestOrganization();

});

it('returns the authenticated user profile', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'name' => 'صاحب الحساب',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/identity/me');

    $response->assertOk()
        ->assertJsonPath('data.name', 'صاحب الحساب')
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissing(['password']);
});

it('updates own profile fields', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    $response = $this->actingAs($user)
        ->patchJson('/api/identity/me', [
            'name' => 'اسم جديد',
            'locale' => 'en',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'اسم جديد')
        ->assertJsonPath('data.locale', 'en');

    expect($user->fresh()->name)->toBe('اسم جديد');
});

it('forbids guests from reading profiles', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $this->getJson('/api/identity/me')->assertUnauthorized();
});

it('rejects profile updates with an invalid locale length', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    $this->actingAs($user)
        ->patchJson('/api/identity/me', [
            'locale' => 'this-locale-is-way-too-long',
        ])
        ->assertStatus(422);
});

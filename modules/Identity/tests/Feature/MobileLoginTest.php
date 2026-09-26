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

it('logs in with email and returns a bearer token that authenticates a real request', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'email' => 'teacher@example.test',
    ]);

    $response = $this->postJson('/api/identity/login', [
        'identifier' => 'teacher@example.test',
        'password' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email']])
        ->assertJsonPath('user.id', $user->id);

    $token = $response->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('logs in with a phone number identifier', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'phone' => '+201000000001',
    ]);

    $this->postJson('/api/identity/login', [
        'identifier' => '+201000000001',
        'password' => 'password',
    ])->assertCreated()->assertJsonPath('user.id', $user->id);
});

it('registers the device and push token supplied at login', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'email' => 'teacher2@example.test',
    ]);

    $this->postJson('/api/identity/login', [
        'identifier' => 'teacher2@example.test',
        'password' => 'password',
        'device_name' => 'Pixel 9',
        'platform' => 'android',
        'push_token' => str_repeat('d', 64),
    ])->assertCreated();

    expect(UserDevice::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects an unknown identifier without leaking whether the account exists', function (): void {
    /** @var IdentityPestContext $this */
    $this->postJson('/api/identity/login', [
        'identifier' => 'nobody@example.test',
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['identifier']);
});

it('rejects a wrong password', function (): void {
    /** @var IdentityPestContext $this */
    User::factory()->inOrganization($this->organizationId)->create([
        'email' => 'teacher3@example.test',
    ]);

    $this->postJson('/api/identity/login', [
        'identifier' => 'teacher3@example.test',
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['identifier']);
});

it('rejects login for a suspended account', function (): void {
    /** @var IdentityPestContext $this */
    User::factory()->inOrganization($this->organizationId)->suspended()->create([
        'email' => 'suspended@example.test',
    ]);

    $this->postJson('/api/identity/login', [
        'identifier' => 'suspended@example.test',
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['identifier']);
});

it('rejects a request missing the password field', function (): void {
    /** @var IdentityPestContext $this */
    $this->postJson('/api/identity/login', [
        'identifier' => 'teacher@example.test',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
});

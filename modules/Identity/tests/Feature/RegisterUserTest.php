<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    $this->createTestOrganization();
});

it('registers a user and dispatches UserRegistered', function (): void {
    Event::fake([UserRegistered::class]);

    $response = $this->postJson('/api/identity/register', [
        'organization_id' => $this->organizationId,
        'name' => 'طالب تجريبي',
        'email' => 'student@eschool.test',
        'password' => 'Sup3r-Secret!',
        'locale' => 'ar',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'student@eschool.test')
        ->assertJsonPath('data.status', 'active');

    /** @var User $user */
    $user = User::query()->where('email', 'student@eschool.test')->firstOrFail();

    expect(Hash::check('Sup3r-Secret!', $user->password))->toBeTrue()
        ->and($user->organization_id)->toBe($this->organizationId);

    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $e): bool => $e->userId === $user->id);
});

it('rejects a duplicate email with a business rule violation', function (): void {
    User::query()->create([
        'organization_id' => $this->organizationId,
        'name' => 'موجود مسبقًا',
        'email' => 'taken@eschool.test',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/identity/register', [
        'organization_id' => $this->organizationId,
        'name' => 'مكرر',
        'email' => 'TAKEN@eschool.test',
        'password' => 'Sup3r-Secret!',
    ]);

    $response->assertStatus(422);
});

<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Application\Actions\RegisterUser;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $this->createTestOrganization();
});

it('registers a user and dispatches an after-commit UserRegistered event', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    Event::fake([UserRegistered::class]);

    $response = $this->postJson('/api/identity/register', [
        'organization_id' => $this->organizationId,
        'name' => 'طالب تجريبي',
        'email' => 'student@eschool.test',
        'username' => 'eschool.student',
        'password' => 'Sup3r-Secret!',
        'locale' => 'ar',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'student@eschool.test')
        ->assertJsonPath('data.status', 'active');

    /** @var User $user */
    $user = User::query()->where('email', 'student@eschool.test')->firstOrFail();

    expect(Hash::check('Sup3r-Secret!', $user->password))->toBeTrue()
        ->and($user->organization_id)->toBe($this->organizationId)
        ->and($user->username)->toBe('eschool.student');

    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event): bool => $event->userId === $user->id);
    expect(new UserRegistered(
        userId: $user->id,
        organizationId: $user->organization_id,
        email: $user->email,
        username: $user->username,
        phone: $user->phone,
        locale: $user->locale,
    ))->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('does not publish UserRegistered or retain the account when an outer transaction rolls back', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    Event::fake([UserRegistered::class]);
    $email = 'rolled-back@eschool.test';

    DB::beginTransaction();

    try {
        app(RegisterUser::class)->execute([
            'organization_id' => $this->organizationId,
            'name' => 'Rolled Back Account',
            'email' => $email,
            'username' => 'eschool.rolled-back',
            'password' => 'Sup3r-Secret!',
        ]);
    } finally {
        DB::rollBack();
    }

    expect(User::query()->where('email', $email)->exists())->toBeFalse();
    Event::assertNotDispatched(UserRegistered::class);
});

it('rejects a duplicate email with a business rule violation', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    User::query()->create([
        'organization_id' => $this->organizationId,
        'name' => 'موجود مسبقًا',
        'email' => 'taken@eschool.test',
        'username' => 'eschool.taken',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/identity/register', [
        'organization_id' => $this->organizationId,
        'name' => 'مكرر',
        'email' => 'TAKEN@eschool.test',
        'username' => 'eschool.duplicate',
        'password' => 'Sup3r-Secret!',
    ]);

    $response->assertStatus(422);
});

it('registers with a phone when email is unavailable', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $response = $this->postJson('/api/identity/register', [
        'organization_id' => $this->organizationId,
        'name' => 'طالب عبر الهاتف',
        'username' => 'eschool.phone',
        'phone' => '+201001234567',
        'phone_country' => 'EG',
        'password' => 'Sup3r-Secret!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.username', 'eschool.phone')
        ->assertJsonPath('data.phone', '+201001234567')
        ->assertJsonPath('data.email', null);
});

it('requires a username and at least one recovery contact', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $this->postJson('/api/identity/register', [
        'organization_id' => $this->organizationId,
        'name' => 'طلب ناقص',
        'password' => 'Sup3r-Secret!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['username', 'email', 'phone']);
});

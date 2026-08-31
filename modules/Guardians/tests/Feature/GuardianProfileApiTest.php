<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Guardians\Domain\Events\GuardianProfileCreated;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Tests\Support\ApiUser;
use Shared\Testing\Fixtures;

function guardianApiUser(): ApiUser
{
    return (new ApiUser((string) Str::ulid()))->forceFill([
        'organization_id' => Fixtures::organizationId(),
    ]);
}

it('stores a guardian profile over the api and returns 201', function (): void {
    Gate::after(fn (): bool => true);
    Event::fake([GuardianProfileCreated::class]);

    /** @var \Tests\TestCase $this */
    $response = $this->actingAs(guardianApiUser())
        ->postJson('/api/guardians/profiles', [
            'organization_id' => Fixtures::organizationId(),
            'user_id' => Fixtures::userId(),
            'national_id_last4' => '9911',
            'occupation' => 'engineer',
            'preferred_contact_channel' => 'whatsapp',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.national_id_last4', '9911')
        ->assertJsonPath('data.preferred_contact_channel', 'whatsapp');

    Event::assertDispatched(GuardianProfileCreated::class);
});

it('validates the store payload and reports translated errors', function (): void {
    Gate::after(fn (): bool => true);

    /** @var \Tests\TestCase $this */
    $this->actingAs(guardianApiUser())
        ->postJson('/api/guardians/profiles', [
            'organization_id' => '',
            'user_id' => 'short',
            'national_id_last4' => 'abc',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['organization_id', 'user_id', 'national_id_last4']);
});

it('forbids storing a guardian profile without the ability', function (): void {
    Gate::define('guardian.link', fn (): bool => false);
    $profilesBefore = GuardianProfile::query()->count();

    /** @var \Tests\TestCase $this */
    $this->actingAs(guardianApiUser())
        ->postJson('/api/guardians/profiles', [
            'organization_id' => Fixtures::organizationId(),
            'user_id' => Fixtures::userId(),
        ])
        ->assertForbidden();

    expect(GuardianProfile::query()->count())->toBe($profilesBefore);
});

it('updates a guardian profile when the caller owns it', function (): void {
    Gate::after(fn (): bool => true);

    $userId = Fixtures::userId();
    $profile = GuardianProfile::factory()->create(['user_id' => $userId]);

    /** @var \Tests\TestCase $this */
    $this->actingAs((new ApiUser($userId))->forceFill([
        'organization_id' => Fixtures::organizationId(),
    ]))
        ->patchJson("/api/guardians/profiles/{$profile->id}", [
            'occupation' => 'physician',
            'preferred_contact_channel' => 'email',
        ])
        ->assertOk()
        ->assertJsonPath('data.occupation', 'physician')
        ->assertJsonPath('data.preferred_contact_channel', 'email');
});

it('forbids updating a profile owned by another user without the ability', function (): void {
    Gate::define('guardian.link', fn (): bool => false);

    $profile = GuardianProfile::factory()->create();

    /** @var \Tests\TestCase $this */
    $this->actingAs(guardianApiUser())
        ->patchJson("/api/guardians/profiles/{$profile->id}", [
            'occupation' => 'intruder',
        ])
        ->assertForbidden();

    expect($profile->refresh()->occupation)->not->toBe('intruder');
});

it('archives a guardian profile with a mandatory reason', function (): void {
    Gate::after(fn (): bool => true);

    $profile = GuardianProfile::factory()->create();

    /** @var \Tests\TestCase $this */
    $this->actingAs(guardianApiUser())
        ->deleteJson("/api/guardians/profiles/{$profile->id}", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    /** @var \Tests\TestCase $this */
    $this->actingAs(guardianApiUser())
        ->deleteJson("/api/guardians/profiles/{$profile->id}", ['reason' => 'left the school'])
        ->assertNoContent();

    expect(GuardianProfile::query()->whereKey($profile->id)->exists())->toBeFalse()
        ->and(GuardianProfile::withTrashed()->whereKey($profile->id)->exists())->toBeTrue();
});

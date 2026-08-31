<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Guardians\Domain\Events\GuardianLinkedToStudent;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Tests\Support\ApiUser;
use Shared\Testing\Fixtures;
use Tests\TestCase;

function guardianLinkApiUser(): ApiUser
{
    return (new ApiUser((string) Str::ulid()))->forceFill([
        'organization_id' => Fixtures::organizationId(),
    ]);
}

it('links a student over the api and returns 201', function (): void {
    /** @var TestCase $this */
    Gate::after(fn (): bool => true);
    Event::fake([GuardianLinkedToStudent::class]);

    $guardian = GuardianProfile::factory()->create();
    $studentId = Fixtures::studentProfileId();

    $response = $this->actingAs(guardianLinkApiUser())
        ->postJson("/api/guardians/profiles/{$guardian->id}/students", [
            'student_profile_id' => $studentId,
            'relationship' => 'father',
            'is_primary' => true,
            'can_act_for' => true,
            'reason' => 'verified relationship documents',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.student_profile_id', $studentId)
        ->assertJsonPath('data.relationship', 'father')
        ->assertJsonPath('data.is_primary', true);

    Event::assertDispatched(GuardianLinkedToStudent::class);
});

it('rejects an unknown relationship over the api', function (): void {
    /** @var TestCase $this */
    Gate::after(fn (): bool => true);

    $guardian = GuardianProfile::factory()->create();

    $this->actingAs(guardianLinkApiUser())
        ->postJson("/api/guardians/profiles/{$guardian->id}/students", [
            'student_profile_id' => Fixtures::studentProfileId(),
            'relationship' => 'cousin_twice_removed',
            'reason' => 'relationship validation test',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['relationship']);
});

it('verifies a link over the api', function (): void {
    /** @var TestCase $this */
    Gate::after(fn (): bool => true);

    $link = GuardianLink::factory()->create();

    $this->actingAs(guardianLinkApiUser())
        ->postJson("/api/guardians/links/{$link->id}/verify", [
            'reason' => 'reviewed relationship documents',
        ])
        ->assertOk()
        ->assertJsonPath('data.verified_at', fn ($value): bool => is_string($value) && $value !== '');

    expect($link->refresh()->verified_at)->not->toBeNull();
});

it('sets a link as primary over the api and demotes the old primary', function (): void {
    /** @var TestCase $this */
    Gate::after(fn (): bool => true);

    $studentId = Fixtures::studentProfileId();
    $oldPrimary = GuardianLink::factory()->primary()->create(['student_profile_id' => $studentId]);
    $newPrimary = GuardianLink::factory()->create(['student_profile_id' => $studentId]);

    $this->actingAs(guardianLinkApiUser())
        ->postJson("/api/guardians/links/{$newPrimary->id}/primary", [
            'reason' => 'the family selected a new primary contact',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    expect($oldPrimary->refresh()->is_primary)->toBeFalse();
});

it('unlinks a student over the api with a mandatory reason', function (): void {
    /** @var TestCase $this */
    Gate::after(fn (): bool => true);

    $link = GuardianLink::factory()->create();

    $this->actingAs(guardianLinkApiUser())
        ->deleteJson("/api/guardians/links/{$link->id}", ['reason' => 'custody changed'])
        ->assertNoContent();

    expect(GuardianLink::query()->whereKey($link->id)->exists())->toBeFalse()
        ->and(GuardianLink::withTrashed()->whereKey($link->id)->exists())->toBeTrue();
});

it('scopes the links list to the caller when lacking view_any', function (): void {
    /** @var TestCase $this */
    Gate::define('guardian.view', fn (): bool => false);

    $userId = Fixtures::userId();
    $own = GuardianProfile::factory()->create(['user_id' => $userId]);
    $other = GuardianProfile::factory()->create();

    GuardianLink::factory()->count(2)->create(['guardian_profile_id' => $own->id]);
    GuardianLink::factory()->create(['guardian_profile_id' => $other->id]);

    $this->actingAs((new ApiUser($userId))->forceFill([
        'organization_id' => Fixtures::organizationId(),
    ]))
        ->getJson('/api/guardians/links')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Domain\Models\User;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
});

it('shows the authenticated teacher profile and account settings', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create(['name' => 'أحمد المعلم']);
    Fixtures::staffProfileForUser($teacher->id);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/profile');

    $response->assertOk()
        ->assertJsonPath('teacher.name', 'أحمد المعلم')
        ->assertJsonPath('account.name', 'أحمد المعلم')
        ->assertJsonPath('account.email', $teacher->email);
});

it('updates the profile name and phone', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create(['name' => 'قديم']);
    Fixtures::staffProfileForUser($teacher->id);

    $response = $this->actingAs($teacher)->patchJson('/api/teacher/profile', [
        'name' => 'جديد',
        'phone' => '+201234567890',
    ]);

    $response->assertOk()->assertJsonPath('account.name', 'جديد');

    expect($teacher->fresh()->name)->toBe('جديد')
        ->and($teacher->fresh()->phone)->toBe('+201234567890');
});

it('rejects a profile update with an over-long name', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($teacher->id);

    $this->actingAs($teacher)
        ->patchJson('/api/teacher/profile', ['name' => str_repeat('a', 200)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('changes the password with the correct current password', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create([
        'password' => Hash::make('OldPass123!'),
    ]);
    Fixtures::staffProfileForUser($teacher->id);

    $response = $this->actingAs($teacher)->putJson('/api/teacher/profile/password', [
        'current_password' => 'OldPass123!',
        'password' => 'NewPass456!',
    ]);

    $response->assertOk()->assertJsonPath('status', 'updated');

    expect(Hash::check('NewPass456!', $teacher->fresh()->password))->toBeTrue();
});

it('rejects a password change with the wrong current password', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create([
        'password' => Hash::make('OldPass123!'),
    ]);
    Fixtures::staffProfileForUser($teacher->id);

    $response = $this->actingAs($teacher)->putJson('/api/teacher/profile/password', [
        'current_password' => 'WrongPass!',
        'password' => 'NewPass456!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'identity.current_password_wrong');

    expect(Hash::check('OldPass123!', $teacher->fresh()->password))->toBeTrue();
});

it('returns a null teacher block for a user with no staff profile', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create(['name' => 'بلا ملف تعليمي']);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/profile');

    $response->assertOk()
        ->assertJsonPath('teacher', null)
        ->assertJsonPath('account.name', 'بلا ملف تعليمي');
});

it('requires authentication', function (): void {
    $this->getJson('/api/teacher/profile')->assertUnauthorized();
    $this->patchJson('/api/teacher/profile', ['name' => 'x'])->assertUnauthorized();
    $this->putJson('/api/teacher/profile/password', [
        'current_password' => 'a',
        'password' => 'b',
    ])->assertUnauthorized();
});

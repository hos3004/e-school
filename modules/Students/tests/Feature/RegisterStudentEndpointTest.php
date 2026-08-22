<?php

declare(strict_types=1);

use Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Students\Domain\Models\StudentProfile;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('students.create', fn ($user) => true);
    Gate::define('students.view_any', fn ($user) => true);
});

function validPayload(): array
{
    return [
        'organization_id' => (string) str()->ulid(),
        'user_id' => (string) str()->ulid(),
        'student_code' => 'STU-F-'.str()->random(4),
        'date_of_birth' => '2011-03-02',
        'gender' => 'female',
        'nationality' => 'EG',
        'country' => 'EG',
        'city' => 'Cairo',
        'preferred_language' => 'ar',
        'joined_at' => '2026-02-01',
    ];
}

it('creates a student through the API and returns 201', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/students', validPayload());

    $response->assertCreated()
        ->assertJsonPath('data.student_code', fn (string $code): bool => str_starts_with($code, 'STU-F-'));

    expect(StudentProfile::query()->whereKey($response->json('data.id'))->exists())->toBeTrue();
});

it('rejects duplicate user registration with a translated message', function () {
    $user = User::factory()->create();
    StudentProfile::query()->create(array_merge(validPayload(), ['id' => (string) str()->ulid()]));

    $payload = validPayload();
    $payload['student_code'] = 'STU-X-'.str()->random(4);

    $this->actingAs($user)
        ->postJson('/api/students', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

it('validates the student code format', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/students', validPayload(['student_code' => 'invalid code!']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['student_code']);
});

it('forbids creation without the students.create ability', function () {
    Gate::define('students.create', fn ($user) => false);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/students', validPayload())
        ->assertForbidden();
});

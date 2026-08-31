<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Models\Program;
use Modules\Identity\Domain\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('program.manage', fn ($user) => true);
});

function programPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'PRG-'.strtoupper(str()->random(5)),
        'name' => ['ar' => 'برنامج جديد', 'en' => 'New Program'],
        'default_session_minutes' => 60,
        'default_rate' => 5000,
        'currency' => 'EGP',
        'program_type' => 'ongoing',
        'target_gender' => 'all',
        'reason' => 'إنشاء برنامج للاختبار',
    ], $overrides);
}

it('creates a program through the API and returns 201', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/academics/programs', programPayload());

    $response->assertCreated()
        ->assertJsonPath('data.currency', 'EGP');

    expect(Program::query()->whereKey($response->json('data.id'))->exists())->toBeTrue();
});

it('rejects duplicate program codes with a validation error', function (): void {
    $user = User::factory()->create();
    Program::factory()->create(['code' => 'DUP-CODE']);

    $this->actingAs($user)
        ->postJson('/api/academics/programs', programPayload(['code' => 'DUP-CODE']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('validates the currency size on program creation', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/academics/programs', programPayload(['currency' => 'EGYP']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});

it('forbids program creation without the create ability', function (): void {
    Gate::define('program.manage', fn ($user) => false);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/academics/programs', programPayload())
        ->assertForbidden();
});

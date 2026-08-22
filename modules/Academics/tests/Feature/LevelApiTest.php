<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Identity\Domain\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('academics.levels.create', fn ($user) => true);
    Gate::define('academics.levels.update', fn ($user) => true);
    Gate::define('academics.levels.reorder', fn ($user) => true);
});

function levelPayload(array $overrides = []): array
{
    return array_merge([
        'program_id' => Program::factory()->create()->getKey(),
        'code' => 'LVL-'.strtoupper(str()->random(4)),
        'name' => ['ar' => 'مستوى جديد', 'en' => 'New Level'],
        'sort_order' => 1,
    ], $overrides);
}

it('creates a level through the API and returns 201', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/academics/levels', levelPayload());

    $response->assertCreated()
        ->assertJsonPath('data.sort_order', 1);

    expect(Level::query()->whereKey($response->json('data.id'))->exists())->toBeTrue();
});

it('rejects a duplicate level code inside the same program', function (): void {
    $user = User::factory()->create();
    $payload = levelPayload();
    Level::factory()->create(['program_id' => $payload['program_id'], 'code' => $payload['code']]);

    $this->actingAs($user)
        ->postJson('/api/academics/levels', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('updates a level through the API', function (): void {
    $user = User::factory()->create();
    $level = Level::factory()->create();

    $this->actingAs($user)
        ->putJson("/api/academics/levels/{$level->getKey()}", [
            'sort_order' => 7,
        ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 7);

    expect($level->fresh()->sort_order)->toBe(7);
});

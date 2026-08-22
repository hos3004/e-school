<?php

declare(strict_types=1);

use Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('academics.levels.reorder', fn ($user) => true);
});

it('reorders levels of a program through the API', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();

    $first = Level::factory()->for($program, 'program')->create(['sort_order' => 1]);
    $second = Level::factory()->for($program, 'program')->create(['sort_order' => 2]);

    $this->actingAs($user)
        ->postJson('/api/academics/levels/reorder', [
            'program_id' => (string) $program->getKey(),
            'level_ids' => [
                (string) $second->getKey(),
                (string) $first->getKey(),
            ],
        ])
        ->assertNoContent();

    expect($second->fresh()->sort_order)->toBe(1)
        ->and($first->fresh()->sort_order)->toBe(2);
});

it('rejects reorder with a level from another program', function () {
    $user = User::factory()->create();
    $program = Program::factory()->create();
    $foreign = Level::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/academics/levels/reorder', [
            'program_id' => (string) $program->getKey(),
            'level_ids' => [(string) $foreign->getKey()],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['level_ids.0']);
});

it('rejects reorder without the ability', function () {
    Gate::define('academics.levels.reorder', fn ($user) => false);
    $user = User::factory()->create();
    $program = Program::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/academics/levels/reorder', [
            'program_id' => (string) $program->getKey(),
            'level_ids' => [(string) str()->ulid()],
        ])
        ->assertForbidden();
});

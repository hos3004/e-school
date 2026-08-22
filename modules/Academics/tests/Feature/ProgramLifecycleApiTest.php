<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Events\ProgramArchived;
use Modules\Academics\Domain\Models\Program;
use Modules\Identity\Domain\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Gate::define('academics.programs.update', fn ($user) => true);
    Gate::define('academics.programs.archive', fn ($user) => true);
});

it('updates a program through the API', function (): void {
    $user = User::factory()->create();
    $program = Program::factory()->create(['duration_weeks' => 10]);

    $this->actingAs($user)
        ->putJson("/api/academics/programs/{$program->getKey()}", [
            'duration_weeks' => 20,
        ])
        ->assertOk()
        ->assertJsonPath('data.duration_weeks', 20);

    expect($program->fresh()->duration_weeks)->toBe(20);
});

it('archives a program with a documented reason', function (): void {
    Event::fake([ProgramArchived::class]);

    $user = User::factory()->create();
    $program = Program::factory()->create();

    $response = $this->actingAs($user)
        ->deleteJson("/api/academics/programs/{$program->getKey()}", [
            'reason' => 'إيقاف لانتهاء الطلب',
        ]);

    expect($program->fresh()->trashed())->toBeTrue()
        ->and($response->status())->toBeIn([200, 204]);

    Event::assertDispatched(
        ProgramArchived::class,
        fn (ProgramArchived $event): bool => $event->reason === 'إيقاف لانتهاء الطلب',
    );
});

it('rejects archiving without a reason', function (): void {
    $user = User::factory()->create();
    $program = Program::factory()->create();

    $this->actingAs($user)
        ->deleteJson("/api/academics/programs/{$program->getKey()}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

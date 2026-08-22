<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Application\Actions\UpdateProgramAction;
use Modules\Academics\Domain\Events\ProgramUpdated;
use Modules\Academics\Domain\Models\Program;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

it('updates program fields and publishes ProgramUpdated with changed fields', function (): void {
    Event::fake([ProgramUpdated::class]);

    $program = Program::factory()->create();

    $updated = app(UpdateProgramAction::class)->execute($program, [
        'duration_weeks' => 24,
        'default_rate' => 9000,
    ]);

    expect($updated->duration_weeks)->toBe(24)
        ->and($updated->default_rate)->toBe(9000);

    Event::assertDispatched(
        ProgramUpdated::class,
        fn (ProgramUpdated $event): bool => $event->programId === (string) $program->getKey()
            && $event->changedFields === ['duration_weeks', 'default_rate'],
    );
});

it('does not publish an event when nothing changed', function (): void {
    Event::fake([ProgramUpdated::class]);

    $program = Program::factory()->create(['currency' => 'EGP']);

    app(UpdateProgramAction::class)->execute($program, ['currency' => 'EGP']);

    Event::assertNotDispatched(ProgramUpdated::class);
});

it('rejects taking another program code', function (): void {
    Program::factory()->create(['code' => 'TAKEN']);

    $program = Program::factory()->create(['code' => 'MINE']);

    app(UpdateProgramAction::class)->execute($program, ['code' => 'TAKEN']);
})->throws(BusinessRuleViolation::class);

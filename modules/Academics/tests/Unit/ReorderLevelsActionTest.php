<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academics\Application\Actions\ReorderLevelsAction;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;

uses(RefreshDatabase::class);

it('reorders levels of a program', function () {
    $program = Program::factory()->create();

    $first = Level::factory()->for($program, 'program')->create(['sort_order' => 5]);
    $second = Level::factory()->for($program, 'program')->create(['sort_order' => 9]);

    app(ReorderLevelsAction::class)->execute((string) $program->getKey(), [
        (string) $second->getKey(),
        (string) $first->getKey(),
    ]);

    expect($second->fresh()->sort_order)->toBe(1)
        ->and($first->fresh()->sort_order)->toBe(2);
});

it('rejects levels that do not belong to the program', function () {
    $program = Program::factory()->create();
    $foreign = Level::factory()->create();

    app(ReorderLevelsAction::class)->execute((string) $program->getKey(), [
        (string) $foreign->getKey(),
    ]);
})->throws(Shared\Support\BusinessRuleViolation::class);

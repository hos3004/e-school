<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Application\Actions\CreateProgramAction;
use Modules\Academics\Domain\Events\ProgramCreated;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

function programData(array $overrides = []): array
{
    return array_merge([
        'organization_id' => Fixtures::organizationId(),
        'code' => 'PRG-TEST',
        'name' => ['ar' => 'برنامج تجريبي', 'en' => 'Demo Program'],
        'duration_weeks' => 16,
        'default_session_minutes' => 60,
        'default_rate' => 7500,
        'currency' => 'EGP',
        'is_active' => true,
    ], $overrides);
}

it('creates a program and publishes ProgramCreated', function (): void {
    Event::fake([ProgramCreated::class]);

    $program = app(CreateProgramAction::class)->execute(programData());

    expect($program->exists)->toBeTrue()
        ->and($program->code)->toBe('PRG-TEST')
        ->and($program->default_rate)->toBe(7500)
        ->and($program->name['ar'])->toBe('برنامج تجريبي');

    Event::assertDispatched(
        ProgramCreated::class,
        fn (ProgramCreated $event): bool => $event->programId === (string) $program->getKey()
            && $event->payload()['default_rate'] === 7500
            && $event->payload()['currency'] === 'EGP',
    );
});

it('rejects a duplicate program code even across archived programs', function (): void {
    $action = app(CreateProgramAction::class);

    $first = $action->execute(programData());
    $first->delete();

    $action->execute(programData());
})->throws(BusinessRuleViolation::class);

it('rejects a negative default rate', function (): void {
    Event::fake([ProgramCreated::class]);

    app(CreateProgramAction::class)->execute(programData(['default_rate' => -1]));
})->throws(BusinessRuleViolation::class);

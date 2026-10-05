<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Application\Actions\CreateLevelAction;
use Modules\Academics\Domain\Events\LevelCreated;
use Modules\Academics\Domain\Models\Program;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function levelData(array $overrides = []): array
{
    return array_merge([
        'organization_id' => Fixtures::organizationId(),
        'program_id' => Program::factory()->create()->getKey(),
        'code' => 'L1',
        'name' => ['ar' => 'المستوى الأول', 'en' => 'Level One'],
        'sort_order' => 1,
    ], $overrides);
}

it('creates a level and publishes LevelCreated', function (): void {
    Event::fake([LevelCreated::class]);

    $level = app(CreateLevelAction::class)->execute(levelData());

    expect($level->exists)->toBeTrue()
        ->and($level->sort_order)->toBe(1);

    Event::assertDispatched(
        LevelCreated::class,
        fn (LevelCreated $event): bool => $event->levelId === (string) $level->getKey()
            && $event->payload()['program_id'] === (string) $level->program_id,
    );
});

it('rejects a duplicate level code within the same program', function (): void {
    $data = levelData();

    app(CreateLevelAction::class)->execute($data);
    app(CreateLevelAction::class)->execute($data);
})->throws(BusinessRuleViolation::class);

it('allows the same level code in different programs', function (): void {
    $data = levelData();

    app(CreateLevelAction::class)->execute($data);

    $other = app(CreateLevelAction::class)->execute(
        levelData(['program_id' => Program::factory()->create()->getKey()]),
    );

    expect($other->exists)->toBeTrue();
});

it('rejects a level for a missing program', function (): void {
    app(CreateLevelAction::class)->execute(levelData([
        'program_id' => (string) str()->ulid(),
    ]));
})->throws(BusinessRuleViolation::class);

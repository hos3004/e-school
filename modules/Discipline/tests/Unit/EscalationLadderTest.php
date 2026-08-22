<?php

declare(strict_types=1);

use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Modules\Discipline\Domain\Services\EscalationLadder;

it('returns null below the first threshold', function (): void {
    $ladder = new EscalationLadder([
        ['threshold' => 1, 'action' => 'notice'],
        ['threshold' => 2, 'action' => 'warning'],
        ['threshold' => 3, 'action' => 'freeze_enrollment', 'automatic' => true],
    ]);

    expect($ladder->resolveForCount(0))->toBeNull();
});

it('applies the highest reached threshold', function (): void {
    $ladder = new EscalationLadder([
        ['threshold' => 1, 'action' => 'notice'],
        ['threshold' => 2, 'action' => 'warning'],
        ['threshold' => 3, 'action' => 'freeze_enrollment', 'automatic' => true],
    ]);

    expect($ladder->resolveForCount(1)['action'])->toBe(DisciplineActionType::Notice)
        ->and($ladder->resolveForCount(2)['action'])->toBe(DisciplineActionType::Warning)
        ->and($ladder->resolveForCount(3)['action'])->toBe(DisciplineActionType::FreezeEnrollment)
        ->and($ladder->resolveForCount(3)['automatic'])->toBeTrue()
        ->and($ladder->resolveForCount(9)['threshold_reached'])->toBe(3);
});

it('reads the production ladder from config with no hardcoded numbers', function (): void {
    $configured = (array) config('discipline.ladder');

    expect($configured)->not->toBeEmpty();

    $ladder = new EscalationLadder;

    foreach ($configured as $entry) {
        expect($ladder->resolveForCount((int) $entry['threshold'])['action'])
            ->toBe(DisciplineActionType::fromLadderEntry($entry));
    }
});

it('ignores unknown action names instead of guessing', function (): void {
    $ladder = new EscalationLadder([
        ['threshold' => 5, 'action' => 'not_a_real_action'],
    ]);

    expect($ladder->resolveForCount(10))->toBeNull();
});

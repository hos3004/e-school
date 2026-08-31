<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reporting\Application\Actions\RecordOrganizationSnapshotAction;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

function snapshotData(): array
{
    return [
        'snapshot_date' => '2026-08-22',
        'students_active' => 120,
        'students_frozen' => 5,
        'teachers_active' => 18,
        'sessions_held' => 64,
        'sessions_cancelled' => 7,
        'attendance_rate_bp' => 8650,
    ];
}

it('records a daily snapshot for the organization', function (): void {
    $snapshot = app(RecordOrganizationSnapshotAction::class)->execute(Fixtures::organizationId(), snapshotData());

    expect($snapshot->exists)->toBeTrue()
        ->and((int) $snapshot->sessions_held)->toBe(64)
        ->and((int) $snapshot->attendance_rate_bp)->toBe(8650)
        ->and(OrganizationSnapshot::query()->count())->toBe(1);
});

it('updates the same snapshot instead of duplicating it (idempotent upsert)', function (): void {
    $action = app(RecordOrganizationSnapshotAction::class);

    $first = $action->execute(Fixtures::organizationId(), snapshotData());
    $second = $action->execute(Fixtures::organizationId(), [...snapshotData(), 'sessions_held' => 70]);

    expect((string) $second->getKey())->toBe((string) $first->getKey())
        ->and((int) $second->sessions_held)->toBe(70)
        ->and(OrganizationSnapshot::query()->count())->toBe(1);
});

it('clamps the attendance rate into the 0..10000 range', function (): void {
    $snapshot = app(RecordOrganizationSnapshotAction::class)->execute(Fixtures::organizationId(), [
        ...snapshotData(),
        'attendance_rate_bp' => 20000,
    ]);

    expect((int) $snapshot->attendance_rate_bp)->toBe(10000);
});

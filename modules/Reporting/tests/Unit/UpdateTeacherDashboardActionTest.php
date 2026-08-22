<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Reporting\Application\Actions\UpdateTeacherDashboardAction;
use Modules\Reporting\Domain\Events\TeacherDashboardUpdated;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function teacherDelta(array $overrides = []): array
{
    return array_merge([
        'organization_id' => Fixtures::organizationId(),
        'staff_profile_id' => Fixtures::staffProfileId(),
        'metric' => 'sessions_completed',
    ], $overrides);
}

it('creates the dashboard and counts a completed session', function (): void {
    Event::fake([TeacherDashboardUpdated::class]);

    $dashboard = app(UpdateTeacherDashboardAction::class)->execute(
        teacherDelta(['at' => now()->toIso8601String()]),
    );

    expect($dashboard->exists)->toBeTrue()
        ->and((int) $dashboard->sessions_total)->toBe(1)
        ->and((int) $dashboard->sessions_completed)->toBe(1)
        ->and($dashboard->last_session_at)->not->toBeNull();

    Event::assertDispatched(TeacherDashboardUpdated::class);
});

it('credits payout as integer minor units without floats', function (): void {
    Event::fake();

    $action = app(UpdateTeacherDashboardAction::class);
    $delta = teacherDelta(['metric' => 'payout_credited', 'amount_minor' => 60000]);

    $dashboard = $action->execute($delta);
    $dashboard = $action->execute(array_merge($delta, ['staff_profile_id' => (string) $dashboard->staff_profile_id, 'amount_minor' => 250]));

    expect((int) $dashboard->payout_minor)->toBe(60250)
        ->and((string) $dashboard->currency)->toBe('EGP')
        ->and($dashboard->payout()->toMajor())->toBe('602.50');
});

it('rejects a negative payout delta', function (): void {
    app(UpdateTeacherDashboardAction::class)->execute(
        teacherDelta(['metric' => 'payout_credited', 'amount_minor' => -5]),
    );
})->throws(BusinessRuleViolation::class);

it('rejects an unknown metric', function (): void {
    app(UpdateTeacherDashboardAction::class)->execute(teacherDelta(['metric' => 'mystery_metric']));
})->throws(BusinessRuleViolation::class);

it('counts cancellations and postponements separately', function (): void {
    Event::fake();

    $action = app(UpdateTeacherDashboardAction::class);
    $delta = teacherDelta();

    $dashboard = $action->execute($delta);
    $id = (string) $dashboard->staff_profile_id;

    $action->execute(array_merge($delta, ['staff_profile_id' => $id, 'metric' => 'cancellation_by_self']));
    $dashboard = $action->execute(array_merge($delta, ['staff_profile_id' => $id, 'metric' => 'session_postponed']));

    expect((int) $dashboard->sessions_total)->toBe(2)
        ->and((int) $dashboard->cancellations_by_self)->toBe(1)
        ->and((int) $dashboard->postponements)->toBe(1)
        ->and((int) $dashboard->sessions_completed)->toBe(1);
});

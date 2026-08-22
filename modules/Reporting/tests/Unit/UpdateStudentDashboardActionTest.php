<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Reporting\Application\Actions\UpdateStudentDashboardAction;
use Modules\Reporting\Domain\Events\StudentDashboardUpdated;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

/**
 * دلتا إسقاط صالحة — تُنشئ اللوحة عند أول استدعاء.
 *
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function studentDelta(array $overrides = []): array
{
    return array_merge([
        'organization_id' => Fixtures::organizationId(),
        'enrollment_id' => (string) str()->ulid(),
        'student_profile_id' => Fixtures::studentProfileId(),
        'metric' => 'sessions_attended',
    ], $overrides);
}

it('creates the dashboard on first projection and recomputes the rate', function (): void {
    Event::fake([StudentDashboardUpdated::class]);

    $delta = studentDelta(['at' => '2026-08-22T12:00:00Z']);
    $dashboard = app(UpdateStudentDashboardAction::class)->execute($delta);

    expect($dashboard->exists)->toBeTrue()
        ->and((int) $dashboard->sessions_attended)->toBe(1)
        ->and((int) $dashboard->attendance_rate_bp)->toBe(10000)
        ->and($dashboard->last_session_at)->not->toBeNull();

    Event::assertDispatched(StudentDashboardUpdated::class);
});

it('aggregates counters across repeated projections', function (): void {
    Event::fake();

    $action = app(UpdateStudentDashboardAction::class);
    $delta = studentDelta();

    $action->execute($delta);
    $action->execute(studentDelta(['enrollment_id' => $delta['enrollment_id'], 'student_profile_id' => $delta['student_profile_id'], 'metric' => 'sessions_missed']));
    $dashboard = $action->execute(studentDelta(['enrollment_id' => $delta['enrollment_id'], 'student_profile_id' => $delta['student_profile_id']]));

    expect((int) $dashboard->sessions_attended)->toBe(2)
        ->and((int) $dashboard->sessions_missed)->toBe(1)
        ->and((int) $dashboard->attendance_rate_bp)->toBe(6667);
});

it('rejects a delta missing its keys', function (): void {
    app(UpdateStudentDashboardAction::class)->execute(['metric' => 'sessions_attended']);
})->throws(BusinessRuleViolation::class);

it('rejects an unknown metric', function (): void {
    app(UpdateStudentDashboardAction::class)->execute(studentDelta(['metric' => 'mystery_metric']));
})->throws(BusinessRuleViolation::class);

it('keeps one row per enrollment even under different students', function (): void {
    expect(StudentDashboard::query()->count())->toBe(0);
});

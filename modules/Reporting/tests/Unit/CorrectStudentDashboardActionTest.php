<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reporting\Application\Actions\CorrectStudentDashboardAction;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

function correctionData(): array
{
    return [
        'enrollment_id' => (string) str()->ulid(),
        'column' => 'sessions_attended',
        'value' => 5,
        'reason' => 'تصحيح بعد تدقيق سجل الحضور الأسبوعي',
    ];
}

it('corrects a counter with a valid reason and recomputes the rate', function (): void {
    $dashboard = StudentDashboard::factory()->create([
        'sessions_attended' => 2,
        'sessions_missed' => 2,
    ]);

    $corrected = app(CorrectStudentDashboardAction::class)->execute([
        ...correctionData(),
        'enrollment_id' => (string) $dashboard->enrollment_id,
    ]);

    expect((int) $corrected->sessions_attended)->toBe(5)
        ->and((int) $corrected->attendance_rate_bp)->toBe(7143);
});

it('rejects a too-short reason', function (): void {
    config()->set('reporting.correction.reason_min_chars', 10);

    app(CorrectStudentDashboardAction::class)->execute([
        ...correctionData(),
        'reason' => 'قصير',
    ]);
})->throws(BusinessRuleViolation::class);

it('rejects a column outside the correctable list', function (): void {
    app(CorrectStudentDashboardAction::class)->execute([
        ...correctionData(),
        'column' => 'organization_id',
    ]);
})->throws(BusinessRuleViolation::class);

it('rejects a negative value', function (): void {
    app(CorrectStudentDashboardAction::class)->execute([
        ...correctionData(),
        'value' => -3,
    ]);
})->throws(BusinessRuleViolation::class);

it('rejects a correction for a missing dashboard', function (): void {
    app(CorrectStudentDashboardAction::class)->execute(correctionData());
})->throws(BusinessRuleViolation::class);

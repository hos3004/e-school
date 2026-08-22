<?php

declare(strict_types=1);

use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;

it('allows only the linear lifecycle draft to approved to sent', function (): void {
    expect(MonthlyReportStatus::Draft->canTransitionTo(MonthlyReportStatus::Approved))->toBeTrue()
        ->and(MonthlyReportStatus::Approved->canTransitionTo(MonthlyReportStatus::Sent))->toBeTrue()
        ->and(MonthlyReportStatus::Draft->canTransitionTo(MonthlyReportStatus::Sent))->toBeFalse()
        ->and(MonthlyReportStatus::Approved->canTransitionTo(MonthlyReportStatus::Draft))->toBeFalse();
});

it('treats sent as a terminal state', function (): void {
    expect(MonthlyReportStatus::Sent->isTerminal())->toBeTrue()
        ->and(MonthlyReportStatus::Draft->isTerminal())->toBeFalse()
        ->and(MonthlyReportStatus::Approved->isTerminal())->toBeFalse()
        ->and(MonthlyReportStatus::Sent->allowedTransitions())->toBe([]);
});

it('resolves from stored string values', function (): void {
    expect(MonthlyReportStatus::from('draft'))->toBe(MonthlyReportStatus::Draft)
        ->and(MonthlyReportStatus::from('approved'))->toBe(MonthlyReportStatus::Approved)
        ->and(MonthlyReportStatus::from('sent'))->toBe(MonthlyReportStatus::Sent);
});

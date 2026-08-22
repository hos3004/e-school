<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\AcademicReports\Domain\Events\MonthlyReportSent;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إرسال التقرير الشهري المعتمد للطالب ووليّ الأمر — الحالة النهائية.
 */
final readonly class SendMonthlyReportAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(MonthlyReport $report): MonthlyReport
    {
        $this->guardTransition($report);

        $this->transaction->run(function () use ($report): void {
            $report->forceFill([
                'status' => MonthlyReportStatus::Sent,
                'sent_at' => CarbonImmutable::now('UTC'),
            ])->save();
        });

        $this->events->dispatch(new MonthlyReportSent(
            monthlyReportId: $report->id,
            organizationId: (string) $report->organization_id,
            studentProfileId: (string) $report->student_profile_id,
            periodYear: (int) $report->period_year,
            periodMonth: (int) $report->period_month,
        ));

        return $report->refresh();
    }

    private function guardTransition(MonthlyReport $report): void
    {
        /** @var MonthlyReportStatus $current */
        $current = $report->status;

        if (!$current->canTransitionTo(MonthlyReportStatus::Sent)) {
            throw BusinessRuleViolation::make(
                'academicreports.monthly_report.invalid_transition',
                'academicreports::errors.monthly_report_invalid_transition',
                ['from' => $current->value, 'to' => MonthlyReportStatus::Sent->value],
            );
        }
    }
}

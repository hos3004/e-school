<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\AcademicReports\Domain\Events\MonthlyReportApproved;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * اعتماد المشرف للتقرير الشهري — الانتقال من draft إلى approved فقط.
 *
 * الاعتماد تغيير على السجل الأكاديمي، لذا السبب إلزامي ويُدوَّن في التدقيق.
 */
final readonly class ApproveMonthlyReportAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(MonthlyReport $report, string $approverId, string $reason): MonthlyReport
    {
        $this->guardTransition($report);

        $this->transaction->run(function () use ($report, $approverId): void {
            $report->forceFill([
                'status' => MonthlyReportStatus::Approved,
                'approved_by' => $approverId,
                'approved_at' => CarbonImmutable::now('UTC'),
            ])->save();
        });

        $this->events->dispatch(new MonthlyReportApproved(
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

        if (!$current->canTransitionTo(MonthlyReportStatus::Approved)) {
            throw BusinessRuleViolation::make(
                'academicreports.monthly_report.invalid_transition',
                'academicreports::errors.monthly_report_invalid_transition',
                ['from' => $current->value, 'to' => MonthlyReportStatus::Approved->value],
            );
        }
    }
}

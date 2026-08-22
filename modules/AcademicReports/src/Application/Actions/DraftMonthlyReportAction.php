<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\AcademicReports\Domain\Events\MonthlyReportDrafted;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * توليد التقرير الشهري مسوّدةً لطالب واحد في شهر محدد.
 *
 * تقرير واحد فقط لكل (طالب، سنة، شهر) — فرادة مضمونة على مستوى قاعدة
 * البيانات ومستوى قواعد العمل معًا.
 */
final readonly class DraftMonthlyReportAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed> $metrics
     */
    public function execute(
        string $organizationId,
        string $studentProfileId,
        string $enrollmentId,
        int $periodYear,
        int $periodMonth,
        array $metrics = [],
        ?string $supervisorSummary = null,
    ): MonthlyReport {
        $this->guardUniquePeriod($studentProfileId, $periodYear, $periodMonth);

        /** @var MonthlyReport $report */
        $report = $this->transaction->run(function () use (
            $organizationId,
            $studentProfileId,
            $enrollmentId,
            $periodYear,
            $periodMonth,
            $metrics,
            $supervisorSummary,
        ): MonthlyReport {
            return MonthlyReport::query()->create([
                'organization_id' => $organizationId,
                'student_profile_id' => $studentProfileId,
                'enrollment_id' => $enrollmentId,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'metrics' => $metrics,
                'supervisor_summary' => $supervisorSummary,
                'status' => MonthlyReportStatus::Draft,
            ]);
        });

        $this->events->dispatch(new MonthlyReportDrafted(
            monthlyReportId: $report->id,
            organizationId: $organizationId,
            studentProfileId: $studentProfileId,
            enrollmentId: $enrollmentId,
            periodYear: $periodYear,
            periodMonth: $periodMonth,
        ));

        return $report->refresh();
    }

    private function guardUniquePeriod(
        string $studentProfileId,
        int $periodYear,
        int $periodMonth,
    ): void {
        $exists = MonthlyReport::query()
            ->forStudent($studentProfileId)
            ->inPeriod($periodYear, $periodMonth)
            ->exists();

        if ($exists) {
            throw BusinessRuleViolation::make(
                'academicreports.monthly_report.duplicate_period',
                'academicreports::errors.monthly_report_duplicate_period',
                ['year' => $periodYear, 'month' => $periodMonth],
            );
        }
    }
}

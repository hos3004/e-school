<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Events;

/**
 * وُلّد التقرير الشهري مسوّدةً — في انتظار اعتماد المشرف.
 */
final class MonthlyReportDrafted extends AcademicReportsEvent
{
    public function __construct(
        public readonly string $monthlyReportId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly string $enrollmentId,
        public readonly int $periodYear,
        public readonly int $periodMonth,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academicreports.monthly_report_drafted';
    }

    public function payload(): array
    {
        return [
            'monthly_report_id' => $this->monthlyReportId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'enrollment_id' => $this->enrollmentId,
            'period_year' => $this->periodYear,
            'period_month' => $this->periodMonth,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Events;

/**
 * أُرسل التقرير الشهري للطالب ووليّ الأمر — حالة نهائية.
 */
final class MonthlyReportSent extends AcademicReportsEvent
{
    public function __construct(
        public readonly string $monthlyReportId,
        public readonly string $organizationId,
        public readonly string $studentProfileId,
        public readonly int $periodYear,
        public readonly int $periodMonth,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'academicreports.monthly_report_sent';
    }

    public function payload(): array
    {
        return [
            'monthly_report_id' => $this->monthlyReportId,
            'organization_id' => $this->organizationId,
            'student_profile_id' => $this->studentProfileId,
            'period_year' => $this->periodYear,
            'period_month' => $this->periodMonth,
        ];
    }
}

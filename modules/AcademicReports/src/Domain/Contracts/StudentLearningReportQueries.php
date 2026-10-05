<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Contracts;

use Modules\AcademicReports\Domain\ValueObjects\StudentLearningReportData;

interface StudentLearningReportQueries
{
    /**
     * Only submitted, student-specific reports for the supplied organization sessions.
     *
     * @param list<string> $sessionIds
     * @return list<StudentLearningReportData>
     */
    public function forStudent(string $organizationId, string $studentProfileId, array $sessionIds, ?string $staffProfileId = null): array;
}

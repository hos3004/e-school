<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Contracts;

use Modules\AcademicReports\Domain\ValueObjects\SessionReportStatusData;

/** قراءة مجمعة لحالة تقرير المعلم دون تسريب نموذج Eloquent. */
interface SessionReportStatusQueries
{
    /**
     * @param list<string> $sessionIds
     * @return array<string, SessionReportStatusData> session id => status
     */
    public function forSessions(array $sessionIds): array;
}

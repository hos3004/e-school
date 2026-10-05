<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Queries;

use Modules\AcademicReports\Domain\Contracts\SessionReportStatusQueries;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Domain\ValueObjects\SessionReportStatusData;

final readonly class SessionReportStatusQueryService implements SessionReportStatusQueries
{
    public function forSessions(array $sessionIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $sessionIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($ids === []) {
            return [];
        }

        return SessionReport::query()
            ->whereIn('session_id', $ids)
            ->get(['session_id', 'submitted_at', 'is_late'])
            ->mapWithKeys(static fn (SessionReport $report): array => [
                (string) $report->session_id => new SessionReportStatusData(
                    sessionId: (string) $report->session_id,
                    submittedAt: $report->submitted_at?->toIso8601String(),
                    isLate: (bool) $report->is_late,
                ),
            ])
            ->all();
    }
}

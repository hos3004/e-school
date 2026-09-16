<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\AcademicReports\Domain\Contracts\SessionReportBatchQueries;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Reporting\Domain\Contracts\ProgramSessionReportDigestQueries;
use Modules\Reporting\Domain\ValueObjects\ProgramSessionReportDigestData;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\ValueObjects\SessionAdministrationData;

final readonly class ProgramSessionReportDigestQueryService implements ProgramSessionReportDigestQueries
{
    public function __construct(
        private SessionAdministrationQueries $sessions,
        private AcademicCatalogQueries $catalog,
        private SessionReportBatchQueries $reports,
    ) {}

    public function forOrganizationInRange(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
        ?string $programId = null,
    ): array {
        if ($untilUtcExclusive->lessThanOrEqualTo($fromUtc)) {
            return [];
        }

        $rows = [];
        $afterStart = null;
        $afterId = null;
        $maxRows = (int) config('reporting.operational.max_rows', 10000);
        $pageSize = (int) config('reporting.operational.scan_page_size', 250);

        do {
            /** @var list<SessionAdministrationData> $page */
            $page = $this->sessions->forReport(
                organizationId: $organizationId,
                fromUtc: $fromUtc,
                untilUtcExclusive: $untilUtcExclusive,
                statuses: [SessionStatus::Completed->value],
                courseId: null,
                limit: $pageSize,
                afterScheduledStart: $afterStart,
                afterId: $afterId,
            );

            foreach ($page as $session) {
                $rows[$session->id] = $session;
            }

            if ($page === [] || count($rows) >= $maxRows) {
                break;
            }

            $last = $page[count($page) - 1];
            $afterStart = CarbonImmutable::parse($last->scheduledStart);
            $afterId = $last->id;
        } while (count($page) === $pageSize);

        if ($rows === []) {
            return [];
        }

        $courseIds = array_values(array_unique(array_map(
            static fn (SessionAdministrationData $session): string => $session->courseId,
            $rows,
        )));
        $courses = $this->catalog->coursesByIds($organizationId, $courseIds);

        $programIdBySession = [];
        foreach ($rows as $sessionId => $session) {
            $resolvedProgramId = $courses[$session->courseId]->programId ?? null;
            if ($resolvedProgramId === null) {
                continue;
            }
            if ($programId !== null && $resolvedProgramId !== $programId) {
                continue;
            }
            $programIdBySession[$sessionId] = $resolvedProgramId;
        }

        if ($programIdBySession === []) {
            return [];
        }

        $reports = $this->reports->forSessions(array_keys($programIdBySession));

        $result = [];
        foreach ($reports as $report) {
            $resolvedProgramId = $programIdBySession[$report->sessionId] ?? null;
            if ($resolvedProgramId === null) {
                continue;
            }

            foreach ($report->students as $student) {
                $result[] = new ProgramSessionReportDigestData(
                    programId: $resolvedProgramId,
                    studentProfileId: $student->studentProfileId,
                    sessionId: $report->sessionId,
                    submittedAt: $report->submittedAt,
                    topicsCovered: $report->topicsCovered,
                    homeworkAssigned: $report->homeworkAssigned,
                    generalNotes: $report->generalNotes,
                    participation: $student->participation,
                    performance: $student->performance,
                    commitment: $student->commitment,
                    strengths: $student->strengths,
                    weaknesses: $student->weaknesses,
                    note: $student->note,
                );
            }
        }

        usort($result, static fn (
            ProgramSessionReportDigestData $left,
            ProgramSessionReportDigestData $right,
        ): int => $left->submittedAt <=> $right->submittedAt);

        return $result;
    }
}

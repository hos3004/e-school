<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Queries;

use Modules\AcademicReports\Domain\Contracts\SessionReportBatchQueries;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Domain\ValueObjects\SessionReportBatchData;
use Modules\AcademicReports\Domain\ValueObjects\SessionReportStudentEntryData;

final readonly class SessionReportBatchQueryService implements SessionReportBatchQueries
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
            ->submitted()
            ->with('students')
            ->orderBy('submitted_at')
            ->get()
            ->map(static function (SessionReport $report): SessionReportBatchData {
                $students = $report->students
                    ->map(static fn ($student): SessionReportStudentEntryData => new SessionReportStudentEntryData(
                        studentProfileId: (string) $student->student_profile_id,
                        participation: $student->getAttribute('participation'),
                        performance: $student->getAttribute('performance'),
                        commitment: $student->getAttribute('commitment'),
                        strengths: $student->getAttribute('strengths'),
                        weaknesses: $student->getAttribute('weaknesses'),
                        note: $student->getAttribute('note'),
                    ))
                    ->values()
                    ->all();

                return new SessionReportBatchData(
                    sessionId: (string) $report->session_id,
                    staffProfileId: (string) $report->staff_profile_id,
                    submittedAt: $report->submitted_at?->toIso8601String() ?? '',
                    isLate: (bool) $report->is_late,
                    topicsCovered: $report->topics_covered,
                    homeworkAssigned: $report->homework_assigned,
                    generalNotes: $report->general_notes,
                    nextSessionPlan: $report->next_session_plan,
                    students: $students,
                );
            })
            ->values()
            ->all();
    }
}

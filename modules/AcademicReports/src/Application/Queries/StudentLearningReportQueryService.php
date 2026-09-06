<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Queries;

use Modules\AcademicReports\Domain\Contracts\StudentLearningReportQueries;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Domain\ValueObjects\StudentLearningReportData;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

final readonly class StudentLearningReportQueryService implements StudentLearningReportQueries
{
    public function __construct(private SessionAdministrationQueries $sessions, private StudentDirectoryQueries $students) {}

    public function forStudent(string $organizationId, string $studentProfileId, array $sessionIds, ?string $staffProfileId = null): array
    {
        if ($sessionIds === [] || $this->students->find($organizationId, $studentProfileId) === null) {
            return [];
        }
        // Validate organization even when the caller supplies identifiers from a different scope.
        $allowed = array_values(array_intersect($sessionIds, $this->sessions->sessionIdsForOrganization($organizationId)));
        $reports = SessionReport::query()->whereIn('session_id', $allowed)->submitted()
            ->when($staffProfileId !== null, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->with(['students' => fn ($query) => $query->where('student_profile_id', $studentProfileId)])
            ->orderByDesc('submitted_at')->get(['id', 'session_id', 'submitted_at']);
        $rows = [];
        foreach ($reports as $report) {
            foreach ($report->students as $student) {
                $rows[] = new StudentLearningReportData(
                    (string) $student->id, (string) $report->session_id, $report->submitted_at->toIso8601String(),
                    $student->getAttribute('participation'), $student->getAttribute('performance'), $student->getAttribute('commitment'),
                    $student->getAttribute('strengths'), $student->getAttribute('weaknesses'), $student->getAttribute('note'),
                );
            }
        }

        return $rows;
    }
}

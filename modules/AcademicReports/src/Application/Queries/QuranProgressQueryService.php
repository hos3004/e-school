<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Queries;

use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;

/** Submitted learning progress only; private supervisor notes never leave this boundary. */
final readonly class QuranProgressQueryService
{
    public function __construct(private SessionAdministrationQueries $sessions) {}

    /** @param list<string> $sessionIds
     * @return array<string, array<string, mixed>>
     */
    public function forSessions(string $organizationId, array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }
        $owned = array_values(array_intersect($sessionIds, $this->sessions->sessionIdsForOrganization($organizationId)));

        return SessionReport::query()->submitted()->whereIn('session_id', $owned)->with('students')->get()
            ->mapWithKeys(static fn (SessionReport $report): array => [(string) $report->session_id => [
                'topics' => $report->getAttribute('topics_covered'), 'next_plan' => $report->getAttribute('next_session_plan'),
                'submitted_at' => $report->submitted_at?->toIso8601String(),
                'students' => $report->students->mapWithKeys(static fn ($student): array => [(string) $student->getAttribute('student_profile_id') => [
                    'note' => $student->getAttribute('note'), 'strengths' => $student->getAttribute('strengths'),
                    'participation' => $student->getAttribute('participation'), 'performance' => $student->getAttribute('performance'), 'commitment' => $student->getAttribute('commitment'),
                ]])->all(),
            ]])->all();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Events\SubstituteCandidatesUpdated;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\TeacherApology;
use Modules\Sessions\Domain\Services\SubstituteCandidateFinder;
use Shared\Support\Transaction;

final readonly class SearchSubstituteCandidatesAction
{
    public function __construct(
        private Transaction $transaction,
        private SubstituteCandidateFinder $finder,
        private AuditRecorder $audit,
        private Dispatcher $events,
    ) {}

    /** @return list<string> */
    public function execute(TeacherApology $apology): array
    {
        if ($apology->status !== ApologyStatus::Approved) {
            return [];
        }

        $session = Session::query()->find((string) $apology->session_id);
        if (!$session instanceof Session || $session->scheduled_end->isPast()) {
            return [];
        }

        $candidateIds = collect($this->finder->candidatesFor((string) $session->id))
            ->filter(static fn (array $candidate): bool => $candidate['is_qualified'] && $candidate['is_available'])
            ->pluck('staff_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $previous = array_values((array) ($apology->substitute_candidate_ids ?? []));
        $firstSearch = $apology->substitute_search_started_at === null;
        $changed = $previous !== $candidateIds;
        $now = CarbonImmutable::now('UTC');

        $this->transaction->run(function () use (
            $apology,
            $candidateIds,
            $previous,
            $firstSearch,
            $changed,
            $now,
        ): void {
            $apology->forceFill([
                'substitute_search_started_at' => $apology->substitute_search_started_at ?? $now,
                'last_substitute_search_at' => $now,
                'substitute_candidate_ids' => $candidateIds,
                'substitute_candidate_count' => count($candidateIds),
            ])->save();

            if ($firstSearch || $changed) {
                $this->audit->record(
                    organizationId: (string) $apology->organization_id,
                    actorId: null,
                    actorType: 'system',
                    action: 'sessions.substitute_search_updated',
                    auditableType: 'teacher_apologies',
                    auditableId: (string) $apology->id,
                    oldValues: ['candidate_ids' => $previous],
                    newValues: [
                        'candidate_ids' => $candidateIds,
                        'candidate_count' => count($candidateIds),
                        'searched_at' => $now->toIso8601String(),
                    ],
                    reason: (string) __('sessions::messages.substitute_search_updated'),
                );
            }
        });

        if ($firstSearch || $changed) {
            $this->events->dispatch(new SubstituteCandidatesUpdated(
                sessionId: (string) $session->id,
                organizationId: (string) $session->organization_id,
                courseId: (string) $session->course_id,
                staffProfileId: (string) $apology->staff_profile_id,
                apologyId: (string) $apology->id,
                candidateStaffProfileIds: $candidateIds,
                searchedAt: $now->toIso8601String(),
            ));
        }

        return $candidateIds;
    }
}

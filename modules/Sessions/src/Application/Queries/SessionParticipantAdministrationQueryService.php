<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;

final readonly class SessionParticipantAdministrationQueryService implements SessionParticipantAdministrationQueries
{
    public function findForOrganization(
        string $organizationId,
        string $participantId,
    ): ?SessionParticipantAdministrationData {
        $participant = $this->baseQuery($organizationId)
            ->whereKey($participantId)
            ->first();

        return $participant === null ? null : self::data($participant);
    }

    public function find(string $participantId): ?SessionParticipantAdministrationData
    {
        /** @var SessionParticipant|null $participant */
        $participant = SessionParticipant::query()
            ->with('session')
            ->whereKey($participantId)
            ->first();

        return $participant === null ? null : self::data($participant);
    }

    public function byIds(string $organizationId, array $participantIds): array
    {
        $participantIds = array_values(array_unique(array_filter(
            $participantIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));
        if ($participantIds === []) {
            return [];
        }

        return $this->baseQuery($organizationId)
            ->whereKey($participantIds)
            ->get()
            ->mapWithKeys(static fn (SessionParticipant $participant): array => [
                (string) $participant->getKey() => self::data($participant),
            ])->all();
    }

    public function forSession(string $organizationId, string $sessionId): array
    {
        return $this->baseQuery($organizationId)
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get()
            ->map(static fn (SessionParticipant $participant): SessionParticipantAdministrationData => self::data($participant))
            ->values()
            ->all();
    }

    public function forSessions(string $organizationId, array $sessionIds): array
    {
        $sessionIds = array_values(array_unique(array_filter(
            $sessionIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($sessionIds === []) {
            return [];
        }

        /** @var array<string, list<SessionParticipantAdministrationData>> $participantsBySession */
        $participantsBySession = array_fill_keys($sessionIds, []);

        $this->baseQuery($organizationId)
            ->whereIn('session_id', $sessionIds)
            ->orderBy('session_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(static function (SessionParticipant $participant) use (&$participantsBySession): void {
                $sessionId = (string) $participant->session_id;

                if (array_key_exists($sessionId, $participantsBySession)) {
                    $participantsBySession[$sessionId][] = self::data($participant);
                }
            });

        return $participantsBySession;
    }

    public function participantIdsForOrganization(string $organizationId): array
    {
        return $this->baseQuery($organizationId)
            ->pluck('session_participants.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /** @return Builder<SessionParticipant> */
    private function baseQuery(string $organizationId): Builder
    {
        return SessionParticipant::query()
            ->with('session')
            ->whereHas('session', static fn (Builder $query): Builder => $query
                ->where('organization_id', $organizationId));
    }

    private static function data(SessionParticipant $participant): SessionParticipantAdministrationData
    {
        $session = $participant->session;

        return new SessionParticipantAdministrationData(
            id: (string) $participant->getKey(),
            organizationId: (string) $session->organization_id,
            sessionId: (string) $participant->session_id,
            studentProfileId: (string) $participant->student_profile_id,
            enrollmentId: (string) $participant->enrollment_id,
            courseId: (string) $session->course_id,
            groupId: $session->group_id === null ? null : (string) $session->group_id,
            staffProfileId: (string) $session->staff_profile_id,
            sessionTitle: is_array($session->title) ? $session->title : [],
            sessionStatus: $session->status->value,
            scheduledStart: $session->scheduled_start->toIso8601String(),
            scheduledEnd: $session->scheduled_end->toIso8601String(),
            firstJoinedAt: $participant->first_joined_at?->toIso8601String(),
            lastLeftAt: $participant->last_left_at?->toIso8601String(),
            attendedMinutes: (int) $participant->attended_minutes,
            excusedAt: $participant->excused_at?->toIso8601String(),
            invitationActive: $participant->revoked_at === null && !$participant->trashed(),
        );
    }
}

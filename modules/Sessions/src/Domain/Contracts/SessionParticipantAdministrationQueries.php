<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Contracts;

use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;

interface SessionParticipantAdministrationQueries
{
    public function findForOrganization(
        string $organizationId,
        string $participantId,
    ): ?SessionParticipantAdministrationData;

    /** للاستهلاك الداخلي الموثوق عندما لا يحمل حدث الفصل المؤسسة صراحةً. */
    public function find(string $participantId): ?SessionParticipantAdministrationData;

    /**
     * @param list<string> $participantIds
     * @return array<string, SessionParticipantAdministrationData>
     */
    public function byIds(string $organizationId, array $participantIds): array;

    /** @return list<SessionParticipantAdministrationData> */
    public function forSession(string $organizationId, string $sessionId): array;

    /**
     * @param list<string> $sessionIds
     * @return array<string, list<SessionParticipantAdministrationData>>
     */
    public function forSessions(string $organizationId, array $sessionIds): array;

    /** @return list<string> */
    public function participantIdsForOrganization(string $organizationId): array;
}

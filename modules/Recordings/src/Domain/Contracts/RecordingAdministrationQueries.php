<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Contracts;

use Modules\Recordings\Domain\ValueObjects\RecordingAdministrationData;

interface RecordingAdministrationQueries
{
    public function findForOrganization(
        string $organizationId,
        string $recordingId,
    ): ?RecordingAdministrationData;

    /** @return list<RecordingAdministrationData> */
    public function forSession(string $organizationId, string $sessionId): array;

    /** @param list<string> $groupIds */
    public function hasActiveGrantFor(
        string $organizationId,
        string $recordingId,
        string $userId,
        array $groupIds,
    ): bool;
}

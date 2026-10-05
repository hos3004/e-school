<?php

declare(strict_types=1);

namespace Modules\Attendance\Domain\Contracts;

use Modules\Attendance\Domain\ValueObjects\AttendanceAdministrationData;

interface AttendanceAdministrationQueries
{
    /**
     * @param list<string> $participantIds
     * @return array<string, AttendanceAdministrationData> session_participant_id => attendance
     */
    public function byParticipantIds(string $organizationId, array $participantIds): array;

    public function findForOrganization(
        string $organizationId,
        string $attendanceId,
    ): ?AttendanceAdministrationData;
}

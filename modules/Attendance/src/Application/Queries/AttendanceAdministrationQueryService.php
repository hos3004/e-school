<?php

declare(strict_types=1);

namespace Modules\Attendance\Application\Queries;

use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Domain\ValueObjects\AttendanceAdministrationData;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;

final readonly class AttendanceAdministrationQueryService implements AttendanceAdministrationQueries
{
    public function __construct(private SessionParticipantAdministrationQueries $participants) {}

    public function byParticipantIds(string $organizationId, array $participantIds): array
    {
        $ownedIds = array_keys($this->participants->byIds($organizationId, $participantIds));
        if ($ownedIds === []) {
            return [];
        }

        return Attendance::query()
            ->whereIn('session_participant_id', $ownedIds)
            ->get()
            ->mapWithKeys(static fn (Attendance $attendance): array => [
                (string) $attendance->session_participant_id => self::data($attendance),
            ])->all();
    }

    public function findForOrganization(
        string $organizationId,
        string $attendanceId,
    ): ?AttendanceAdministrationData {
        /** @var Attendance|null $attendance */
        $attendance = Attendance::query()->whereKey($attendanceId)->first();
        if ($attendance === null || $this->participants->findForOrganization(
            $organizationId,
            (string) $attendance->session_participant_id,
        ) === null) {
            return null;
        }

        return self::data($attendance);
    }

    private static function data(Attendance $attendance): AttendanceAdministrationData
    {
        return new AttendanceAdministrationData(
            id: (string) $attendance->getKey(),
            sessionParticipantId: (string) $attendance->session_participant_id,
            status: $attendance->status->value,
            derivedStatus: $attendance->derived_status->value,
            attendedMinutes: (int) $attendance->attended_minutes,
            joinedAfterMinutes: (int) $attendance->joined_after_minutes,
            leftBeforeMinutes: (int) $attendance->left_before_minutes,
            confirmedBy: $attendance->confirmed_by === null ? null : (string) $attendance->confirmed_by,
            confirmedAt: $attendance->confirmed_at?->toIso8601String(),
            overrideReason: $attendance->override_reason,
        );
    }
}

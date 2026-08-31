<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Queries;

use Illuminate\Database\Eloquent\Builder;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\ValueObjects\SessionSchedulingData;
use Shared\ValueObjects\TimeRange;

final readonly class SessionSchedulingQueryService implements SessionSchedulingQueries
{
    public function find(string $organizationId, string $sessionId): ?SessionSchedulingData
    {
        /** @var Session|null $session */
        $session = Session::query()
            ->forOrganization($organizationId)
            ->whereKey($sessionId)
            ->with(['participants' => static fn ($query) => $query->whereNull('revoked_at')])
            ->first();

        return $session === null ? null : self::data($session);
    }

    public function conflictsFor(
        string $organizationId,
        TimeRange $range,
        ?string $staffProfileId = null,
        ?string $groupId = null,
        array $studentProfileIds = [],
        ?string $ignoreSessionId = null,
    ): array {
        $studentProfileIds = array_values(array_unique(array_filter(
            $studentProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($staffProfileId === null && $groupId === null && $studentProfileIds === []) {
            return [];
        }

        return Session::query()
            ->forOrganization($organizationId)
            ->whereNotIn('status', [
                SessionStatus::CancelledByStudent->value,
                SessionStatus::CancelledByTeacher->value,
                SessionStatus::CancelledBySchool->value,
                SessionStatus::Postponed->value,
                SessionStatus::Superseded->value,
            ])
            ->where('scheduled_start', '<', $range->end)
            ->where('scheduled_end', '>', $range->start)
            ->when($ignoreSessionId !== null, static fn (Builder $query): Builder => $query->whereKeyNot($ignoreSessionId))
            ->where(static function (Builder $query) use ($staffProfileId, $groupId, $studentProfileIds): void {
                if ($staffProfileId !== null) {
                    $query->orWhere('staff_profile_id', $staffProfileId);
                }

                if ($groupId !== null) {
                    $query->orWhere('group_id', $groupId);
                }

                if ($studentProfileIds !== []) {
                    $query->orWhereHas('participants', static fn (Builder $participants): Builder => $participants
                        ->whereNull('revoked_at')
                        ->whereIn('student_profile_id', $studentProfileIds));
                }
            })
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    private static function data(Session $session): SessionSchedulingData
    {
        return new SessionSchedulingData(
            id: (string) $session->getKey(),
            organizationId: (string) $session->organization_id,
            scheduleId: $session->schedule_id === null ? null : (string) $session->schedule_id,
            groupId: $session->group_id === null ? null : (string) $session->group_id,
            courseId: (string) $session->course_id,
            staffProfileId: (string) $session->staff_profile_id,
            sessionType: (string) $session->session_type,
            status: $session->status->value,
            scheduledStart: $session->scheduled_start,
            scheduledEnd: $session->scheduled_end,
            title: is_array($session->title) ? $session->title : [],
            studentProfileIds: $session->participants
                ->pluck('student_profile_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Sessions\Domain\ValueObjects\SessionAdministrationData;

final readonly class SessionAdministrationQueryService implements SessionAdministrationQueries
{
    public function findForOrganization(
        string $organizationId,
        string $sessionId,
    ): ?SessionAdministrationData {
        $session = Session::query()
            ->forOrganization($organizationId)
            ->whereKey($sessionId)
            ->first();

        return $session === null ? null : self::data($session);
    }

    public function sessionIdsForOrganization(string $organizationId): array
    {
        return Session::query()
            ->forOrganization($organizationId)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function sessionIdsForTeacher(string $organizationId, string $staffProfileId): array
    {
        return Session::query()
            ->forOrganization($organizationId)
            ->forStaff($staffProfileId)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function organizationIdForSession(string $sessionId): ?string
    {
        $organizationId = Session::query()->whereKey($sessionId)->value('organization_id');

        return $organizationId === null ? null : (string) $organizationId;
    }

    public function upcomingForClassroomProvisioning(
        CarbonImmutable $from,
        CarbonImmutable $until,
        int $limit,
    ): array {
        return Session::query()
            ->whereIn('status', [SessionStatus::Scheduled, SessionStatus::Confirmed])
            ->whereBetween('scheduled_start', [$from, $until])
            ->orderBy('scheduled_start')
            ->limit(max(1, $limit))
            ->get()
            ->map(static fn (Session $session): SessionAdministrationData => self::data($session))
            ->values()
            ->all();
    }

    public function forStudent(string $organizationId, string $studentProfileId, int $limit): array
    {
        $limit = $this->limit($limit);

        return SessionParticipant::query()
            ->forStudent($studentProfileId)
            ->activeInvitation()
            ->whereHas('session', static fn (Builder $query): Builder => $query
                ->where('organization_id', $organizationId))
            ->with('session')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(static function (SessionParticipant $participant): SessionAdministrationData {
                /** @var Session $session */
                $session = $participant->session;

                return self::data($session, (int) $participant->attended_minutes);
            })
            ->values()
            ->all();
    }

    public function forTeacher(string $organizationId, string $staffProfileId, int $limit): array
    {
        $limit = $this->limit($limit);

        return Session::query()
            ->forOrganization($organizationId)
            ->forStaff($staffProfileId)
            ->latest('scheduled_start')
            ->limit($limit)
            ->get()
            ->map(static fn (Session $session): SessionAdministrationData => self::data($session))
            ->values()
            ->all();
    }

    public function forGroup(string $organizationId, string $groupId, int $limit): array
    {
        return Session::query()
            ->forOrganization($organizationId)
            ->where('group_id', $groupId)
            ->latest('scheduled_start')
            ->limit($this->limit($limit))
            ->get()
            ->map(static fn (Session $session): SessionAdministrationData => self::data($session))
            ->values()
            ->all();
    }

    public function forSchedule(string $organizationId, string $scheduleId, int $limit): array
    {
        return Session::query()
            ->forOrganization($organizationId)
            ->where('schedule_id', $scheduleId)
            ->latest('scheduled_start')
            ->limit($this->limit($limit))
            ->get()
            ->map(static fn (Session $session): SessionAdministrationData => self::data($session))
            ->values()
            ->all();
    }

    public function startsForSchedule(string $organizationId, string $scheduleId, int $limit): array
    {
        return Session::query()
            ->forOrganization($organizationId)
            ->where('schedule_id', $scheduleId)
            ->orderBy('scheduled_start')
            ->limit(max(1, $limit))
            ->get(['scheduled_start'])
            ->map(static fn (Session $session): string => $session->scheduled_start->toIso8601String())
            ->values()
            ->all();
    }

    public function forReport(
        string $organizationId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $untilUtcExclusive,
        array $statuses = [],
        ?string $studentProfileId = null,
        ?string $staffProfileId = null,
        ?string $groupId = null,
        ?string $courseId = null,
        array $sessionTypes = [],
        ?string $originalStaffProfileId = null,
        ?int $limit = null,
        ?CarbonImmutable $afterScheduledStart = null,
        ?string $afterId = null,
    ): array {
        if ($untilUtcExclusive->lessThanOrEqualTo($fromUtc)) {
            return [];
        }

        $statuses = self::stringList($statuses);
        $sessionTypes = self::stringList($sessionTypes);

        return Session::query()
            ->forOrganization($organizationId)
            ->where('scheduled_start', '>=', $fromUtc)
            ->where('scheduled_start', '<', $untilUtcExclusive)
            ->when($statuses !== [], static fn (Builder $query): Builder => $query
                ->whereIn('status', $statuses))
            ->when($sessionTypes !== [], static fn (Builder $query): Builder => $query
                ->whereIn('session_type', $sessionTypes))
            ->when($studentProfileId !== null && $studentProfileId !== '', static fn (Builder $query): Builder => $query
                ->whereHas('participants', static fn (Builder $participants): Builder => $participants
                    ->where('student_profile_id', $studentProfileId)))
            ->when($staffProfileId !== null && $staffProfileId !== '', static fn (Builder $query): Builder => $query
                ->where('staff_profile_id', $staffProfileId))
            ->when($originalStaffProfileId !== null && $originalStaffProfileId !== '', static fn (Builder $query): Builder => $query
                ->where('original_teacher_id', $originalStaffProfileId))
            ->when($groupId !== null && $groupId !== '', static fn (Builder $query): Builder => $query
                ->where('group_id', $groupId))
            ->when($courseId !== null && $courseId !== '', static fn (Builder $query): Builder => $query
                ->where('course_id', $courseId))
            ->when(
                $afterScheduledStart !== null && $afterId !== null && $afterId !== '',
                static fn (Builder $query): Builder => $query->where(
                    static fn (Builder $cursor): Builder => $cursor
                        ->where('scheduled_start', '>', $afterScheduledStart)
                        ->orWhere(static fn (Builder $sameStart): Builder => $sameStart
                            ->where('scheduled_start', $afterScheduledStart)
                            ->where('id', '>', $afterId)),
                ),
            )
            ->orderBy('scheduled_start')
            ->orderBy('id')
            ->limit($this->reportLimit($limit))
            ->get()
            ->map(static fn (Session $session): SessionAdministrationData => self::data($session))
            ->values()
            ->all();
    }

    public function labelsForSessions(string $organizationId, array $sessionIds): array
    {
        $sessionIds = array_values(array_unique(array_filter(
            $sessionIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($sessionIds === []) {
            return [];
        }

        $locale = app()->getLocale();

        return Session::query()
            ->forOrganization($organizationId)
            ->whereKey($sessionIds)
            ->get(['id', 'title', 'scheduled_start'])
            ->mapWithKeys(static fn (Session $session): array => [
                (string) $session->getKey() => self::label($session, $locale),
            ])
            ->all();
    }

    public function countsForTeachers(string $organizationId, array $staffProfileIds, CarbonImmutable $monthStart): array
    {
        $staffProfileIds = array_values(array_unique(array_filter(
            $staffProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($staffProfileIds === []) {
            return [];
        }

        $now = CarbonImmutable::now('UTC');
        $monthEnd = $monthStart->addMonth();

        /** @var array<string, array{upcoming: int, completed: int, cancelled: int}> $counts */
        $counts = array_fill_keys($staffProfileIds, ['upcoming' => 0, 'completed' => 0, 'cancelled' => 0]);

        Session::query()
            ->forOrganization($organizationId)
            ->whereIn('staff_profile_id', $staffProfileIds)
            ->reorder()
            ->selectRaw(implode(', ', [
                'staff_profile_id',
                "count(*) filter (where status in ('scheduled', 'confirmed') and scheduled_start >= ?) as upcoming",
                "count(*) filter (where status = 'completed' and scheduled_start >= ? and scheduled_start < ?) as completed",
                "count(*) filter (where status in ('cancelled_by_student', 'cancelled_by_teacher', 'cancelled_by_school') and scheduled_start >= ? and scheduled_start < ?) as cancelled",
            ]), [$now, $monthStart, $monthEnd, $monthStart, $monthEnd])
            ->groupBy('staff_profile_id')
            ->get()
            ->each(static function (mixed $row) use (&$counts): void {
                /** @var object{staff_profile_id: string, upcoming: mixed, completed: mixed, cancelled: mixed} $row */
                $key = (string) $row->staff_profile_id;
                $key = (string) $row->staff_profile_id;

                if (!array_key_exists($key, $counts)) {
                    return;
                }

                $counts[$key] = [
                    'upcoming' => (int) $row->upcoming,
                    'completed' => (int) $row->completed,
                    'cancelled' => (int) $row->cancelled,
                ];
            });

        return $counts;
    }

    private static function label(Session $session, string $locale): string
    {
        $title = is_array($session->title)
            ? (string) ($session->title[$locale] ?? reset($session->title) ?: '')
            : (string) $session->title;

        return trim($title.' — '.$session->scheduled_start?->timezone(config('app.timezone'))->format('Y-m-d H:i'), ' —');
    }

    private function limit(int $requested): int
    {
        return max(1, min($requested, (int) config('sessions.admin_hub.max_items')));
    }

    private function reportLimit(?int $requested): int
    {
        $maximum = max(1, (int) config('sessions.reporting.max_items', 1000));

        return $requested === null ? $maximum : max(1, min($requested, $maximum));
    }

    /**
     * @param array<mixed> $values
     * @return list<string>
     */
    private static function stringList(array $values): array
    {
        return array_values(array_unique(array_filter(
            $values,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        )));
    }

    private static function data(Session $session, ?int $attendedMinutes = null): SessionAdministrationData
    {
        return new SessionAdministrationData(
            id: (string) $session->getKey(),
            organizationId: (string) $session->organization_id,
            groupId: (string) $session->group_id,
            courseId: (string) $session->course_id,
            staffProfileId: (string) $session->staff_profile_id,
            status: $session->status->value,
            title: is_array($session->title) ? $session->title : [],
            scheduledStart: $session->scheduled_start->toIso8601String(),
            scheduledEnd: $session->scheduled_end->toIso8601String(),
            attendedMinutes: $attendedMinutes,
            originalStaffProfileId: $session->original_teacher_id === null
                ? null
                : (string) $session->original_teacher_id,
            sessionType: (string) $session->session_type,
            actualStart: $session->actual_start?->toIso8601String(),
            actualEnd: $session->actual_end?->toIso8601String(),
            cancellationReason: $session->cancellation_reason === null
                ? null
                : (string) $session->cancellation_reason,
            finalizedAt: $session->finalized_at?->toIso8601String(),
        );
    }
}

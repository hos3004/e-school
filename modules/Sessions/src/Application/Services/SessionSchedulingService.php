<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Application\Actions\ScheduleSessionAction;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Sessions\Domain\ValueObjects\ScheduledParticipantData;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\TimeRange;

/** قناة الكتابة العامة بين Scheduling وSessions. */
final readonly class SessionSchedulingService implements SessionSchedulingGateway
{
    use TransitionsSessionStatus;

    public function __construct(
        private ScheduleSessionAction $scheduleSession,
        private SessionSchedulingQueries $queries,
        private AuditRecorder $audit,
    ) {}

    public function createScheduledSession(
        string $organizationId,
        string $scheduleId,
        ?string $groupId,
        string $courseId,
        string $staffProfileId,
        string $sessionType,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $title,
        array $participants,
        ?string $actorId,
    ): string {
        return DB::transaction(function () use (
            $organizationId,
            $scheduleId,
            $groupId,
            $courseId,
            $staffProfileId,
            $sessionType,
            $startsAt,
            $endsAt,
            $title,
            $participants,
            $actorId,
        ): string {
            $this->lockResources($staffProfileId, $groupId, $participants);

            $existingId = Session::query()
                ->forOrganization($organizationId)
                ->where('schedule_id', $scheduleId)
                ->where('scheduled_start', $startsAt)
                ->where('status', '!=', SessionStatus::Superseded->value)
                ->value('id');

            if (is_string($existingId) && $existingId !== '') {
                $this->syncParticipants($existingId, $participants);

                return $existingId;
            }

            $range = new TimeRange($startsAt, $endsAt);
            $studentIds = array_map(
                static fn (ScheduledParticipantData $participant): string => $participant->studentProfileId,
                $participants,
            );
            $conflicts = $this->queries->conflictsFor(
                organizationId: $organizationId,
                range: $range,
                staffProfileId: $staffProfileId,
                groupId: $groupId,
                studentProfileIds: $studentIds,
            );

            if ($conflicts !== []) {
                throw BusinessRuleViolation::make(
                    'scheduling.conflict_detected',
                    'scheduling::errors.conflict_detected',
                    ['count' => count($conflicts)],
                );
            }

            $session = $this->scheduleSession->execute([
                'organization_id' => $organizationId,
                'schedule_id' => $scheduleId,
                'group_id' => $groupId,
                'course_id' => $courseId,
                'staff_profile_id' => $staffProfileId,
                'session_type' => $sessionType,
                'scheduled_start' => $startsAt,
                'scheduled_end' => $endsAt,
                'title' => $title,
            ], $actorId, __('scheduling::messages.generated_from_schedule'));

            $this->insertParticipants((string) $session->getKey(), $participants);

            return (string) $session->getKey();
        });
    }

    public function scheduleMakeup(
        string $organizationId,
        string $originalSessionId,
        CarbonImmutable $startsAt,
        string $actorId,
        string $reason,
    ): string {
        return DB::transaction(function () use ($organizationId, $originalSessionId, $startsAt, $actorId, $reason): string {
            /** @var Session|null $original */
            $original = Session::query()
                ->forOrganization($organizationId)
                ->with('participants')
                ->lockForUpdate()
                ->whereKey($originalSessionId)
                ->first();

            if ($original === null) {
                throw BusinessRuleViolation::make('session.not_found', 'scheduling::errors.session_not_found');
            }

            if (!$original->status->canTransitionTo(SessionStatus::Postponed)) {
                throw BusinessRuleViolation::make(
                    'session.not_postponable',
                    'scheduling::errors.session_not_postponable',
                    ['status' => $original->status->value],
                );
            }

            $duration = (int) $original->scheduled_start->diffInMinutes($original->scheduled_end);
            $fromStatus = $original->status->value;
            $endsAt = $startsAt->addMinutes($duration);
            $participants = $original->participants
                ->map(static fn (SessionParticipant $participant): ScheduledParticipantData => new ScheduledParticipantData(
                    studentProfileId: (string) $participant->student_profile_id,
                    enrollmentId: (string) $participant->enrollment_id,
                ))
                ->values()
                ->all();

            $this->lockResources((string) $original->staff_profile_id, $original->group_id, $participants);

            $conflicts = $this->queries->conflictsFor(
                organizationId: $organizationId,
                range: new TimeRange($startsAt, $endsAt),
                staffProfileId: (string) $original->staff_profile_id,
                groupId: $original->group_id === null ? null : (string) $original->group_id,
                studentProfileIds: array_map(
                    static fn (ScheduledParticipantData $participant): string => $participant->studentProfileId,
                    $participants,
                ),
                ignoreSessionId: $originalSessionId,
            );

            if ($conflicts !== []) {
                throw BusinessRuleViolation::make(
                    'scheduling.conflict_detected',
                    'scheduling::errors.conflict_detected',
                    ['count' => count($conflicts)],
                );
            }

            $this->applyTransition(
                $original,
                SessionStatus::Postponed,
                reason: $reason,
                changedBy: $actorId,
            );

            $makeup = $this->scheduleSession->execute([
                'organization_id' => $organizationId,
                'group_id' => $original->group_id,
                'course_id' => $original->course_id,
                'staff_profile_id' => $original->staff_profile_id,
                'makeup_for_session_id' => $originalSessionId,
                'session_type' => 'makeup',
                'scheduled_start' => $startsAt,
                'scheduled_end' => $endsAt,
                'title' => $original->title,
            ], $actorId, $reason);

            $this->insertParticipants((string) $makeup->getKey(), $participants);

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'sessions.session_postponed',
                auditableType: 'sessions',
                auditableId: $originalSessionId,
                oldValues: ['status' => $fromStatus],
                newValues: [
                    'status' => SessionStatus::Postponed->value,
                    'makeup_session_id' => (string) $makeup->getKey(),
                    'makeup_start' => $startsAt->toIso8601String(),
                    'makeup_end' => $endsAt->toIso8601String(),
                ],
                reason: $reason,
            );

            return (string) $makeup->getKey();
        });
    }

    public function supersedeFutureForSchedule(
        string $organizationId,
        string $scheduleId,
        CarbonImmutable $from,
        string $actorId,
        string $reason,
    ): int {
        return DB::transaction(function () use ($organizationId, $scheduleId, $from, $actorId, $reason): int {
            $sessions = Session::query()
                ->forOrganization($organizationId)
                ->where('schedule_id', $scheduleId)
                ->where('scheduled_start', '>=', $from)
                ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::Confirmed->value])
                ->lockForUpdate()
                ->get();

            foreach ($sessions as $session) {
                $this->applyTransition(
                    $session,
                    SessionStatus::Superseded,
                    reason: $reason,
                    changedBy: $actorId,
                    metadata: ['schedule_id' => $scheduleId],
                );
            }

            return $sessions->count();
        });
    }

    public function addParticipantToFutureGroupSessions(
        string $organizationId,
        string $groupId,
        ?string $courseId,
        string $studentProfileId,
        string $enrollmentId,
    ): int {
        return DB::transaction(function () use (
            $organizationId,
            $groupId,
            $courseId,
            $studentProfileId,
            $enrollmentId,
        ): int {
            $sessions = Session::query()
                ->forOrganization($organizationId)
                ->where('group_id', $groupId)
                ->when($courseId !== null, static fn ($query) => $query->where('course_id', $courseId))
                ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::Confirmed->value])
                ->where('scheduled_start', '>', CarbonImmutable::now('UTC'))
                ->get(['id']);
            $created = 0;

            foreach ($sessions as $session) {
                if (SessionParticipant::query()
                    ->where('session_id', $session->getKey())
                    ->where('student_profile_id', $studentProfileId)
                    ->activeInvitation()
                    ->exists()) {
                    continue;
                }

                $this->insertParticipants((string) $session->getKey(), [
                    new ScheduledParticipantData($studentProfileId, $enrollmentId),
                ]);
                $created++;
            }

            return $created;
        });
    }

    public function revokeParticipantFromFutureGroupSessions(
        string $organizationId,
        string $groupId,
        string $studentProfileId,
        ?string $actorId,
        string $reason,
    ): int {
        return DB::transaction(function () use (
            $organizationId,
            $groupId,
            $studentProfileId,
            $actorId,
            $reason,
        ): int {
            $sessionIds = Session::query()
                ->forOrganization($organizationId)
                ->where('group_id', $groupId)
                ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::Confirmed->value])
                ->where('scheduled_start', '>', CarbonImmutable::now('UTC'))
                ->pluck('id');

            if ($sessionIds->isEmpty()) {
                return 0;
            }

            $participants = SessionParticipant::query()
                ->whereIn('session_id', $sessionIds)
                ->where('student_profile_id', $studentProfileId)
                ->activeInvitation()
                ->get();
            $now = CarbonImmutable::now('UTC');

            foreach ($participants as $participant) {
                $participant->forceFill([
                    'revoked_at' => $now,
                    'revoked_by' => $actorId,
                    'revocation_reason' => trim($reason),
                ])->save();
                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: $actorId === null ? 'system' : 'user',
                    action: 'sessions.participant_invitation_revoked',
                    auditableType: 'session_participants',
                    auditableId: (string) $participant->getKey(),
                    oldValues: ['revoked_at' => null],
                    newValues: [
                        'session_id' => (string) $participant->session_id,
                        'student_profile_id' => $studentProfileId,
                        'revoked_at' => $now->toIso8601String(),
                    ],
                    reason: trim($reason),
                );
            }

            return $participants->count();
        });
    }

    /** @param list<ScheduledParticipantData> $participants */
    private function insertParticipants(string $sessionId, array $participants): void
    {
        foreach ($participants as $participant) {
            /** @var SessionParticipant|null $existing */
            $existing = SessionParticipant::withTrashed()
                ->where('session_id', $sessionId)
                ->where('student_profile_id', $participant->studentProfileId)
                ->first();

            if ($existing !== null) {
                if ($existing->revoked_at === null && !$existing->trashed()) {
                    continue;
                }

                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->forceFill([
                    'enrollment_id' => $participant->enrollmentId,
                    'join_url_token' => Str::random(64),
                    'invited_at' => CarbonImmutable::now('UTC'),
                    'revoked_at' => null,
                    'revoked_by' => null,
                    'revocation_reason' => null,
                ])->save();

                continue;
            }

            SessionParticipant::query()->create([
                'session_id' => $sessionId,
                'student_profile_id' => $participant->studentProfileId,
                'enrollment_id' => $participant->enrollmentId,
                'join_url_token' => Str::random(64),
                'invited_at' => CarbonImmutable::now('UTC'),
                'attended_minutes' => 0,
            ]);
        }
    }

    /** @param list<ScheduledParticipantData> $participants */
    private function syncParticipants(string $sessionId, array $participants): void
    {
        $this->insertParticipants($sessionId, $participants);
    }

    /** @param list<ScheduledParticipantData> $participants */
    private function lockResources(string $staffProfileId, ?string $groupId, array $participants): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $keys = ['teacher:'.$staffProfileId];
        if ($groupId !== null) {
            $keys[] = 'group:'.$groupId;
        }
        foreach ($participants as $participant) {
            $keys[] = 'student:'.$participant->studentProfileId;
        }

        sort($keys);
        foreach (array_unique($keys) as $key) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
        }
    }
}

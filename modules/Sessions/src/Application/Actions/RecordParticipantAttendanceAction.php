<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Events\SessionAttendanceRecorded;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Shared\Support\BusinessRuleViolation;

/**
 * رصد حضور المشاركين: دخول الفصل أو خروج منه واحتساب دقائق الحضور.
 */
final readonly class RecordParticipantAttendanceAction
{
    public function __construct(
        private Dispatcher $events,
        private AuditRecorder $audit,
    ) {}

    public function execute(
        Session $session,
        SessionParticipant $participant,
        string $type,
        ?string $actorId = null,
        ?CarbonImmutable $occurredAt = null,
        bool $allowReconnect = false,
    ): SessionParticipant {
        if ((string) $participant->session_id !== (string) $session->getKey()
            || $participant->revoked_at !== null
            || $participant->trashed()) {
            throw BusinessRuleViolation::make(
                'sessions.participant_not_active',
                'sessions::errors.participant_not_active',
            );
        }

        if (!in_array($type, ['join', 'leave'], true)) {
            throw BusinessRuleViolation::make(
                'sessions.attendance_type_invalid',
                'sessions::errors.attendance_type_invalid',
                ['type' => $type],
            );
        }

        [$participant, $event] = DB::transaction(function () use (
            $session,
            $participant,
            $type,
            $actorId,
            $occurredAt,
            $allowReconnect,
        ): array {
            $now = $occurredAt ?? CarbonImmutable::now('UTC');
            $before = [
                'first_joined_at' => $participant->first_joined_at?->toIso8601String(),
                'last_left_at' => $participant->last_left_at?->toIso8601String(),
                'current_joined_at' => $participant->current_joined_at?->toIso8601String(),
                'attended_seconds' => $participant->attended_seconds,
                'attended_minutes' => $participant->attended_minutes,
            ];

            if ($type === 'join') {
                if (!$allowReconnect && !$session->status->allowsJoining()) {
                    throw BusinessRuleViolation::make(
                        'sessions.not_joinable',
                        'sessions::errors.not_joinable',
                        ['status' => $session->status->label()],
                    );
                }

                if (!$allowReconnect && $participant->first_joined_at !== null) {
                    throw BusinessRuleViolation::make(
                        'sessions.already_joined',
                        'sessions::errors.already_joined',
                    );
                }

                if ($participant->current_joined_at !== null) {
                    return [$participant, null];
                }

                $participant->forceFill([
                    'first_joined_at' => $participant->first_joined_at ?? $now,
                    'current_joined_at' => $now,
                ])->save();
            } else {
                $currentJoinedAt = $participant->current_joined_at;
                if (!$allowReconnect && $currentJoinedAt === null) {
                    $currentJoinedAt = $participant->first_joined_at;
                }

                if ($currentJoinedAt === null) {
                    if ($allowReconnect) {
                        return [$participant, null];
                    }

                    throw BusinessRuleViolation::make(
                        'sessions.leave_without_join',
                        'sessions::errors.leave_without_join',
                    );
                }

                if (!$allowReconnect && $participant->last_left_at !== null) {
                    throw BusinessRuleViolation::make(
                        'sessions.already_left',
                        'sessions::errors.already_left',
                    );
                }

                $intervalStart = CarbonImmutable::instance($currentJoinedAt)
                    ->max($session->scheduled_start);
                $intervalEnd = $now->min($session->scheduled_end);
                $intervalSeconds = $intervalEnd->greaterThan($intervalStart)
                    ? (int) $intervalStart->diffInSeconds($intervalEnd)
                    : 0;
                $attendedSeconds = max(
                    (int) $participant->attended_seconds,
                    (int) $participant->attended_minutes * 60,
                ) + $intervalSeconds;

                $participant->forceFill([
                    'last_left_at' => $now,
                    'current_joined_at' => null,
                    'attended_seconds' => $attendedSeconds,
                    'attended_minutes' => intdiv($attendedSeconds, 60),
                ])->save();
            }

            $this->audit->record(
                organizationId: (string) $session->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'sessions.participant_'.$type,
                auditableType: 'session_participants',
                auditableId: (string) $participant->getKey(),
                oldValues: $before,
                newValues: [
                    'session_id' => (string) $session->getKey(),
                    'student_profile_id' => (string) $participant->student_profile_id,
                    'first_joined_at' => $participant->first_joined_at?->toIso8601String(),
                    'last_left_at' => $participant->last_left_at?->toIso8601String(),
                    'current_joined_at' => $participant->current_joined_at?->toIso8601String(),
                    'attended_seconds' => $participant->attended_seconds,
                    'attended_minutes' => $participant->attended_minutes,
                ],
                reason: __('sessions::messages.attendance_'.$type),
            );

            return [$participant, new SessionAttendanceRecorded(
                sessionId: $session->id,
                organizationId: $session->organization_id,
                courseId: $session->course_id,
                staffProfileId: $session->staff_profile_id,
                participantId: $participant->id,
                studentProfileId: $participant->student_profile_id,
                firstJoinedAt: $participant->first_joined_at?->toIso8601String(),
                lastLeftAt: $participant->last_left_at?->toIso8601String(),
                attendedMinutes: $participant->attended_minutes,
            )];
        });

        if ($event !== null) {
            $this->events->dispatch($event);
        }

        return $participant;
    }
}

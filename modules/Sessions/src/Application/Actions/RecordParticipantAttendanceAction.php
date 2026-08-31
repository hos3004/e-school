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

    public function execute(Session $session, SessionParticipant $participant, string $type, ?string $actorId = null): SessionParticipant
    {
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

        [$participant, $event] = DB::transaction(function () use ($session, $participant, $type, $actorId): array {
            $now = CarbonImmutable::now('UTC');
            $before = [
                'first_joined_at' => $participant->first_joined_at?->toIso8601String(),
                'last_left_at' => $participant->last_left_at?->toIso8601String(),
                'attended_minutes' => $participant->attended_minutes,
            ];

            if ($type === 'join') {
                if (!$session->status->allowsJoining()) {
                    throw BusinessRuleViolation::make(
                        'sessions.not_joinable',
                        'sessions::errors.not_joinable',
                        ['status' => $session->status->label()],
                    );
                }

                if ($participant->first_joined_at !== null) {
                    throw BusinessRuleViolation::make(
                        'sessions.already_joined',
                        'sessions::errors.already_joined',
                    );
                }

                $participant->forceFill(['first_joined_at' => $now])->save();
            } else {
                if ($participant->first_joined_at === null) {
                    throw BusinessRuleViolation::make(
                        'sessions.leave_without_join',
                        'sessions::errors.leave_without_join',
                    );
                }

                if ($participant->last_left_at !== null) {
                    throw BusinessRuleViolation::make(
                        'sessions.already_left',
                        'sessions::errors.already_left',
                    );
                }

                $minutes = (int) round(CarbonImmutable::instance($participant->first_joined_at)->diffInMinutes($now));

                $participant->forceFill([
                    'last_left_at' => $now,
                    'attended_minutes' => max($minutes, 0),
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

        $this->events->dispatch($event);

        return $participant;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\StudentSessionApologized;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class SubmitStudentSessionApologyAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private AuditRecorder $audit,
        private StaffQueries $staff,
        private ExcuseAbsenceAction $excuseSession,
    ) {}

    public function execute(
        string $organizationId,
        string $sessionId,
        string $studentProfileId,
        string $actorId,
        string $reason,
    ): SessionParticipant {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'sessions.student_apology_reason_required',
                'sessions::errors.student_apology_reason_required',
            );
        }

        $participant = SessionParticipant::query()
            ->with('session')
            ->forStudent($studentProfileId)
            ->where('session_id', $sessionId)
            ->whereHas('session', static fn ($query) => $query->where('organization_id', $organizationId))
            ->first();

        if ($participant === null || $participant->revoked_at !== null) {
            throw BusinessRuleViolation::make(
                'sessions.student_apology_participant_not_found',
                'sessions::errors.student_apology_participant_not_found',
            );
        }

        if ($participant->excused_at !== null) {
            throw BusinessRuleViolation::make(
                'sessions.student_apology_already_submitted',
                'sessions::errors.student_apology_already_submitted',
            );
        }

        $session = $participant->session;
        if (!in_array($session->status, [SessionStatus::Scheduled, SessionStatus::Confirmed], true)) {
            throw BusinessRuleViolation::make(
                'sessions.student_apology_session_closed',
                'sessions::errors.student_apology_session_closed',
                ['status' => $session->status->value],
            );
        }

        $required = (int) config('scheduling.student_apology.min_notice_minutes');
        $actual = (int) CarbonImmutable::now('UTC')->diffInMinutes($session->scheduled_start, false);
        if ($actual < $required) {
            throw BusinessRuleViolation::make(
                'sessions.student_apology_notice_not_met',
                'sessions::errors.student_apology_notice_not_met',
                ['required' => $required, 'actual' => max($actual, 0)],
            );
        }

        $now = CarbonImmutable::now('UTC');
        $participant = $this->transaction->run(function () use (
            $participant,
            $organizationId,
            $actorId,
            $reason,
            $now,
        ): SessionParticipant {
            $participant->forceFill([
                'excused_at' => $now,
                'excused_by' => $actorId,
                'excuse_reason' => $reason,
            ])->save();

            $this->audit->record(
                organizationId: $organizationId,
                actorId: $actorId,
                actorType: 'user',
                action: 'sessions.student_apology_submitted',
                auditableType: 'session_participants',
                auditableId: (string) $participant->getKey(),
                oldValues: ['excused_at' => null],
                newValues: ['excused_at' => $now->toIso8601String()],
                reason: $reason,
            );

            return $participant;
        });

        $isGroup = $session->group_id !== null;
        if (!$isGroup) {
            $this->excuseSession->execute($session, $reason, $actorId);
        }

        $this->events->dispatch(new StudentSessionApologized(
            sessionId: (string) $session->getKey(),
            organizationId: (string) $session->organization_id,
            courseId: (string) $session->course_id,
            staffProfileId: (string) $session->staff_profile_id,
            sessionParticipantId: (string) $participant->getKey(),
            studentProfileId: (string) $participant->student_profile_id,
            studentUserId: $actorId,
            teacherUserId: $this->staff->userIdForProfile(
                (string) $session->organization_id,
                (string) $session->staff_profile_id,
            ),
            groupSession: $isGroup,
            reason: $reason,
            actorId: $actorId,
        ));

        return $participant;
    }
}

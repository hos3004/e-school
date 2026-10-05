<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Domain\Events\StudentPingedReady;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Support\BusinessRuleViolation;

/**
 * زر «أنا مستعد» عند الطالب.
 *
 * تنبيه للمعلم فقط — لا يفتح فصلًا ولا يغيّر حالة الحصة أو الحضور أو
 * المستحقات. آخر ضغطة فقط تُحفظ؛ الضغطات السابقة لا تُراكم ولا تُسجَّل.
 */
final readonly class PingSessionReadyAction
{
    public function __construct(
        private Dispatcher $events,
        private StaffQueries $staff,
    ) {}

    public function execute(string $organizationId, string $sessionId, string $studentProfileId, string $actorId): SessionParticipant
    {
        $participant = SessionParticipant::query()
            ->with('session')
            ->forStudent($studentProfileId)
            ->where('session_id', $sessionId)
            ->whereHas('session', static fn ($query) => $query->where('organization_id', $organizationId))
            ->first();

        if ($participant === null || $participant->revoked_at !== null) {
            throw BusinessRuleViolation::make(
                'sessions.ready_ping_participant_not_found',
                'sessions::errors.ready_ping_participant_not_found',
            );
        }

        $session = $participant->session;
        if (!$session->status->allowsJoining()) {
            throw BusinessRuleViolation::make(
                'sessions.ready_ping_session_closed',
                'sessions::errors.ready_ping_session_closed',
                ['status' => $session->status->value],
            );
        }

        $now = CarbonImmutable::now('UTC');
        $participant->forceFill(['ready_pinged_at' => $now])->save();

        $this->events->dispatch(new StudentPingedReady(
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
            readyAt: $now->toIso8601String(),
            actorId: $actorId,
        ));

        return $participant;
    }
}

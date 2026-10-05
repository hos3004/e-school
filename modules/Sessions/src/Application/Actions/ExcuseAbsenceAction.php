<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionExcused;
use Modules\Sessions\Domain\Models\Session;
use Shared\Support\BusinessRuleViolation;

/**
 * قبول غياب الطالب بعذر مقبول من الإدارة.
 */
final readonly class ExcuseAbsenceAction
{
    public function __construct(
        private Dispatcher $events,
        private TransitionSessionStatusAction $transition,
    ) {}

    public function execute(Session $session, string $reason, string $actorId): Session
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'sessions.excuse_reason_required',
                'sessions::errors.reason_required',
            );
        }

        $session = $this->transition->execute(
            $session,
            SessionStatus::Excused,
            $actorId,
            $reason,
            'sessions.session_excused',
        );

        $this->events->dispatch(new SessionExcused(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            reason: $reason,
        ));

        return $session;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionExcused;
use Modules\Sessions\Domain\Models\Session;
use Shared\Support\BusinessRuleViolation;

/**
 * قبول غياب الطالب بعذر مقبول من الإدارة.
 */
final readonly class ExcuseAbsenceAction
{
    use TransitionsSessionStatus;

    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(Session $session, string $reason, ?string $actorId = null): Session
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'sessions.excuse_reason_required',
                'sessions::errors.reason_required',
            );
        }

        $this->guardNotTerminal($session);

        $this->applyTransition(
            $session,
            SessionStatus::Excused,
            reason: $reason,
        );

        $this->events->dispatch(new SessionExcused(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            reason: $reason,
        ));

        return $session->refresh();
    }
}

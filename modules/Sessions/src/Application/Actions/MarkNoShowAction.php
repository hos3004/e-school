<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionNoShowRecorded;
use Modules\Sessions\Domain\Models\Session;

/**
 * رصد غياب الطالب بدون إذن — مخالفة تُحتسب على الطالب.
 */
final readonly class MarkNoShowAction
{
    public function __construct(
        private Dispatcher $events,
        private TransitionSessionStatusAction $transition,
    ) {}

    public function execute(Session $session, string $reason, string $actorId): Session
    {
        $session = $this->transition->execute(
            $session,
            SessionStatus::NoShow,
            $actorId,
            $reason,
            'sessions.session_no_show',
        );

        $this->events->dispatch(new SessionNoShowRecorded(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            reason: $reason,
        ));

        return $session;
    }
}

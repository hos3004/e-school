<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionNoShowRecorded;
use Modules\Sessions\Domain\Models\Session;

/**
 * رصد غياب الطالب بدون إذن — مخالفة تُحتسب على الطالب.
 */
final readonly class MarkNoShowAction
{
    use TransitionsSessionStatus;

    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(Session $session, ?string $reason = null, ?string $actorId = null): Session
    {
        $this->guardNotTerminal($session);

        $this->applyTransition(
            $session,
            SessionStatus::NoShow,
            reason: $reason,
        );

        $this->events->dispatch(new SessionNoShowRecorded(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            reason: $reason,
        ));

        return $session->refresh();
    }
}

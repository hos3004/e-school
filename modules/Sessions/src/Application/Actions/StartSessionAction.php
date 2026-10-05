<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionStarted;
use Modules\Sessions\Domain\Models\Session;

/**
 * فتح الفصل وبدء الحصة فعليًا.
 */
final readonly class StartSessionAction
{
    public function __construct(
        private Dispatcher $events,
        private TransitionSessionStatusAction $transition,
    ) {}

    public function execute(Session $session, string $actorId, string $reason): Session
    {
        $now = CarbonImmutable::now('UTC');

        $session = $this->transition->execute(
            $session,
            SessionStatus::InProgress,
            $actorId,
            $reason,
            'sessions.session_started',
            ['actual_start' => $now->toIso8601String()],
        );

        $this->events->dispatch(new SessionStarted(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            actualStart: $now->toIso8601String(),
        ));

        return $session;
    }
}

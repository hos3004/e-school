<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionEndedForReview;
use Modules\Sessions\Domain\Models\Session;

/**
 * إنهاء الحصة وتركها بانتظار رصد الحضور واعتماد المعلم.
 */
final readonly class EndSessionAction
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
            SessionStatus::AwaitingReview,
            $actorId,
            $reason,
            'sessions.session_ended',
            ['actual_end' => $now->toIso8601String()],
        );

        $this->events->dispatch(new SessionEndedForReview(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            actualEnd: $now->toIso8601String(),
        ));

        return $session;
    }
}

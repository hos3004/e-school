<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionEndedForReview;
use Modules\Sessions\Domain\Models\Session;

/**
 * إنهاء الحصة وتركها بانتظار رصد الحضور واعتماد المعلم.
 */
final readonly class EndSessionAction
{
    use TransitionsSessionStatus;

    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(Session $session, ?string $actorId = null): Session
    {
        $this->guardNotTerminal($session);

        $now = CarbonImmutable::now('UTC');

        $this->applyTransition(
            $session,
            SessionStatus::AwaitingReview,
            ['actual_end' => $now],
        );

        $this->events->dispatch(new SessionEndedForReview(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            actualEnd: $now->toIso8601String(),
        ));

        return $session->refresh();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionStarted;
use Modules\Sessions\Domain\Models\Session;

/**
 * فتح الفصل وبدء الحصة فعليًا.
 */
final readonly class StartSessionAction
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
            SessionStatus::InProgress,
            ['actual_start' => $now],
        );

        $this->events->dispatch(new SessionStarted(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            actualStart: $now->toIso8601String(),
        ));

        return $session->refresh();
    }
}

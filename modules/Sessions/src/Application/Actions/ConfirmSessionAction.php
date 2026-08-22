<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Sessions\Application\Concerns\TransitionsSessionStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionConfirmed;
use Modules\Sessions\Domain\Models\Session;

/**
 * تأكيد الحضور من الطرفين بعد الجدولة.
 */
final readonly class ConfirmSessionAction
{
    use TransitionsSessionStatus;

    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(Session $session, ?string $actorId = null): Session
    {
        $this->guardNotTerminal($session);

        $this->applyTransition($session, SessionStatus::Confirmed);

        $this->events->dispatch(new SessionConfirmed(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
        ));

        return $session->refresh();
    }
}

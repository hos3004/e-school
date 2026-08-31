<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\SessionCompleted;
use Modules\Sessions\Domain\Models\Session;

/**
 * اعتماد الحصة وقفلها نهائيًا — بعدها تُنشأ قيود المستحقات ولا يُعدَّل شيء
 * إلا بقيدة تسوية جديدة.
 */
final readonly class CompleteSessionAction
{
    public function __construct(
        private Dispatcher $events,
        private TransitionSessionStatusAction $transition,
    ) {}

    public function execute(Session $session, string $actorId, string $reason): Session
    {
        DB::transaction(function () use (&$session, $actorId, $reason): void {
            $session = $this->transition->execute(
                $session,
                SessionStatus::Completed,
                $actorId,
                $reason,
                'sessions.session_completed',
                [
                    'finalized_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    'finalized_by' => $actorId,
                ],
            );
        });

        $attended = (int) $session->participants()->sum('attended_minutes');

        $this->events->dispatch(new SessionCompleted(
            sessionId: $session->id,
            organizationId: $session->organization_id,
            courseId: $session->course_id,
            staffProfileId: $session->staff_profile_id,
            attendedMinutes: $attended,
        ));

        return $session;
    }
}

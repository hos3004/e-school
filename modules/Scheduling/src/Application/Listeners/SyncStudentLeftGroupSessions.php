<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Listeners;

use Modules\Groups\Domain\Events\StudentLeftGroup;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;

/** يبطل دعوات الحصص المستقبلية دون حذف سجل المشاركة أو الحضور السابق. */
final readonly class SyncStudentLeftGroupSessions
{
    public function __construct(private SessionSchedulingGateway $sessions) {}

    public function handle(StudentLeftGroup $event): void
    {
        $this->sessions->revokeParticipantFromFutureGroupSessions(
            organizationId: $event->organizationId,
            groupId: $event->groupId,
            studentProfileId: $event->studentProfileId,
            actorId: $event->actorId,
            reason: $event->reason,
        );
    }
}

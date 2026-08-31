<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Listeners;

use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Events\StudentAssignedToGroup;
use Modules\Sessions\Domain\Contracts\SessionSchedulingGateway;

/** يضيف الطالب فورًا إلى الحصص المستقبلية بعد مسار التسكين الآمن. */
final readonly class SyncStudentAssignedToGroupSessions
{
    public function __construct(
        private EnrollmentAdministrationQueries $enrollments,
        private SessionSchedulingGateway $sessions,
    ) {}

    public function handle(StudentAssignedToGroup $event): void
    {
        $enrollmentId = $this->enrollments->schedulableEnrollmentIdsByStudent(
            $event->organizationId,
            $event->programId,
            [$event->studentProfileId],
        )[$event->studentProfileId] ?? null;

        if ($enrollmentId === null) {
            return;
        }

        $this->sessions->addParticipantToFutureGroupSessions(
            organizationId: $event->organizationId,
            groupId: $event->groupId,
            courseId: $event->courseId,
            studentProfileId: $event->studentProfileId,
            enrollmentId: $enrollmentId,
        );
    }
}

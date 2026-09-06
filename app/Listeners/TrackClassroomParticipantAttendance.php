<?php

declare(strict_types=1);

namespace App\Listeners;

use Carbon\CarbonImmutable;
use Modules\Sessions\Domain\Contracts\SessionParticipantAttendanceGateway;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantJoined;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantLeft;

final readonly class TrackClassroomParticipantAttendance
{
    public function __construct(
        private SessionParticipantAttendanceGateway $attendance,
    ) {}

    public function handle(
        ClassroomParticipantJoined|ClassroomParticipantLeft $event,
    ): void {
        if ($event->userId === null || trim($event->userId) === '') {
            return;
        }

        $type = $event instanceof ClassroomParticipantJoined ? 'join' : 'leave';

        $this->attendance->recordProviderEvent(
            sessionId: $event->sessionId,
            userId: $event->userId,
            type: $type,
            occurredAt: CarbonImmutable::parse(
                $event->participantOccurredAt,
                'UTC',
            ),
        );
    }
}

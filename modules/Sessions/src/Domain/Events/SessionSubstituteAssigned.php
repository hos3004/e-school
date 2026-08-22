<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أُسند معلم بديل لحصة.
 *
 * يستمع له:
 *  - Notifications : إشعار البديل والأصلي والطلاب المتأثرين
 *  - VirtualClassroom : نقل صلاحية الإشراف في الفصل للبديل
 *  - Payroll : أجر البديل بسعره، وخصم حصة من الأصلي
 */
final class SessionSubstituteAssigned extends DomainEvent
{
    /**
     * @param list<string> $participantIds
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly string $originalTeacherId,
        public readonly string $substituteTeacherId,
        public readonly string $reason,
        public readonly bool $isOverride,
        public readonly string $scheduledStart,
        public readonly array $participantIds,
        ?string $actorId = null,
    ) {
        parent::__construct($actorId);
    }

    public function name(): string
    {
        return 'sessions.substitute_assigned';
    }

    public function module(): string
    {
        return 'Sessions';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'session_id' => $this->sessionId,
            'original_teacher_id' => $this->originalTeacherId,
            'substitute_teacher_id' => $this->substituteTeacherId,
            'reason' => $this->reason,
            'is_override' => $this->isOverride,
            'scheduled_start' => $this->scheduledStart,
            'participant_ids' => $this->participantIds,
        ];
    }
}

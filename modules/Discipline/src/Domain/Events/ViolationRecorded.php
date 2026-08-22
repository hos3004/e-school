<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Events;

use Modules\Discipline\Domain\Enums\ViolationType;
use Shared\Domain\DomainEvent;

/**
 * سُجِّلت مخالفة جديدة لطالب.
 *
 * يستمع لها موديول Notifications لإبلاغ الطالب ووليّه حسب notify في السُلَّم.
 */
final class ViolationRecorded extends DomainEvent
{
    public function __construct(
        public readonly string $violationId,
        public readonly string $organizationId,
        public readonly string $enrollmentId,
        public readonly string $studentProfileId,
        public readonly ViolationType $type,
        public readonly string $windowKey,
        public readonly int $countInWindow,
        public readonly ?string $sessionId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'discipline.violation_recorded';
    }

    public function module(): string
    {
        return 'Discipline';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'violation_id' => $this->violationId,
            'organization_id' => $this->organizationId,
            'enrollment_id' => $this->enrollmentId,
            'student_profile_id' => $this->studentProfileId,
            'session_id' => $this->sessionId,
            'type' => $this->type->value,
            'countable' => $this->type->isCountable(),
            'window_key' => $this->windowKey,
            'count_in_window' => $this->countInWindow,
        ];
    }
}

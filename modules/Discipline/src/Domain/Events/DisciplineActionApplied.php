<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\Events;

use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Shared\Domain\DomainEvent;

/**
 * طُبِّق إجراء انضباط على تسجيل طالب (تنبيه، إنذار، تجميد...).
 *
 * أهم مستمع: موديول Enrollments — ينفّذ التجميد فعليًا عند freeze_enrollment.
 */
final class DisciplineActionApplied extends DomainEvent
{
    public function __construct(
        public readonly string $disciplineActionId,
        public readonly string $organizationId,
        public readonly string $enrollmentId,
        public readonly DisciplineActionType $action,
        public readonly int $thresholdReached,
        public readonly string $windowKey,
        public readonly bool $isAutomatic,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function name(): string
    {
        return 'discipline.action_applied';
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
            'discipline_action_id' => $this->disciplineActionId,
            'organization_id' => $this->organizationId,
            'enrollment_id' => $this->enrollmentId,
            'action' => $this->action->value,
            'threshold_reached' => $this->thresholdReached,
            'window_key' => $this->windowKey,
            'is_automatic' => $this->isAutomatic,
        ];
    }
}

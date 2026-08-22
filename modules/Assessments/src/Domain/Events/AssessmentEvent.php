<?php

declare(strict_types=1);

namespace Modules\Assessments\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أساس أحداث موديول Assessments — يوحّد الموديول المالك والمعرّفات المشتركة.
 */
abstract class AssessmentEvent extends DomainEvent
{
    public function __construct(
        protected readonly string $assessmentId,
        protected readonly string $organizationId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function module(): string
    {
        return 'assessments';
    }
}

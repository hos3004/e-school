<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أساس أحداث موديول Content — يحمل معرّفات المادة والكورس.
 */
abstract class CourseMaterialEvent extends DomainEvent
{
    public function __construct(
        public readonly string $materialId,
        public readonly string $courseId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function module(): string
    {
        return 'content';
    }
}

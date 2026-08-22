<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * الأساس المشترك لأحداث موديول Sessions.
 *
 * كل حدث يحمل معرّفات فقط — أبدًا نماذج Eloquent.
 */
abstract class SessionEvent extends DomainEvent
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $organizationId,
        public readonly string $courseId,
        public readonly string $staffProfileId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function module(): string
    {
        return 'Sessions';
    }
}

<?php

declare(strict_types=1);

namespace Modules\VirtualClassroom\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Shared\Domain\DomainEvent;

/**
 * أساس أحداث موديول VirtualClassroom — يثبّت المعرّفات المشتركة.
 */
abstract class VirtualClassroomEvent extends DomainEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly string $classroomId,
        public readonly string $sessionId,
        public readonly string $provider,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function module(): string
    {
        return 'virtualclassroom';
    }
}

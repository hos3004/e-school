<?php

declare(strict_types=1);

namespace Modules\Recordings\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * الأساس المشترك لأحداث موديول Recordings.
 *
 * كل حدث يحمل معرّفات وقيَمًا بدائية — أبدًا نماذج Eloquent.
 */
abstract class RecordingEvent extends DomainEvent
{
    public function __construct(
        public readonly string $recordingId,
        public readonly string $organizationId,
        public readonly string $sessionId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function module(): string
    {
        return 'Recordings';
    }
}

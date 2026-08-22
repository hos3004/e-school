<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * الأساس المشترك لأحداث موديول Notifications — يحمل معرّفات الرسالة.
 */
abstract class NotificationEvent extends DomainEvent
{
    public function __construct(
        public readonly string $outboxId,
        public readonly string $organizationId,
        public readonly string $userId,
        ?string $actorId = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($actorId, $correlationId);
    }

    public function module(): string
    {
        return 'notifications';
    }
}

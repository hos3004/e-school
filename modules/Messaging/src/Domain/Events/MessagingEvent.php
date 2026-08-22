<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * الأساس المشترك لأحداث موديول Messaging.
 *
 * كل حدث يحمل معرّفات فقط — أبدًا نماذج Eloquent.
 */
abstract class MessagingEvent extends DomainEvent
{
    public function module(): string
    {
        return 'messaging';
    }
}

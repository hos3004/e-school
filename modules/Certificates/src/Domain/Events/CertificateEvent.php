<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * الأساس المشترك لأحداث موديول الشهادات.
 */
abstract class CertificateEvent extends DomainEvent
{
    public function module(): string
    {
        return 'certificates';
    }
}

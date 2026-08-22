<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Events;

use Shared\Domain\DomainEvent;

/**
 * أساس أحداث موديول التقارير الأكاديمية.
 */
abstract class AcademicReportsEvent extends DomainEvent
{
    public function module(): string
    {
        return 'academicreports';
    }
}

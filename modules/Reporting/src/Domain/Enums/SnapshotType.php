<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Enums;

/**
 * نوع فترة اللقطة التنظيمية — يحدد مستوى التجميع في لوحات المؤسسة.
 */
enum SnapshotType: string
{
    /** لقطة يومية — الأساس الذي تُجمَّع منه بقية الفترات. */
    case Daily = 'daily';

    /** لقطة أسبوعية مُجمَّعة من اليوميات. */
    case Weekly = 'weekly';

    /** لقطة شهرية مُجمَّعة من اليوميات. */
    case Monthly = 'monthly';

    public function label(): string
    {
        return __('reporting::snapshot_type.'.$this->value);
    }
}

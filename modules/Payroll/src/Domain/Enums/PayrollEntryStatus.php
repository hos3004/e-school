<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Enums;

/**
 * دورة حياة قيدة المستحقات.
 *
 * القيدة دفتر أستاذ: القيدَة المسجّلة نهائية لا تُعدَّل ولا تُحذف.
 * الحالة الوحيدة التي تنتقل هي المؤجَّلة — تتحرر عند إقامة حصة التلافي.
 */
enum PayrollEntryStatus: string
{
    /** قيدة نهائية سُجِّلت من حصة مُقامة أو خصم مُطبَّق. */
    case Recorded = 'recorded';

    /** مؤجَّلة حتى إقامة حصة التلافي (قرار العميل: الحصة أُجّلت → مؤجَّل). */
    case Deferred = 'deferred';

    /** حُررت بعد إقامة حصة التلافي. */
    case Released = 'released';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Recorded => [],
            self::Deferred => [self::Released],
            self::Released => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return __('payroll::entry_status.'.$this->value);
    }
}

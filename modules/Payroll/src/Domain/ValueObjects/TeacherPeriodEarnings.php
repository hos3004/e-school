<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\ValueObjects;

/**
 * كشف فترة واحدة لمعلم واحد — قيم أولية فقط.
 *
 * كل المبالغ بالوحدة الصغرى (قروش) وفق `Shared\ValueObjects\Money`؛
 * لا حساب مالي بـfloat في أي طبقة.
 */
final readonly class TeacherPeriodEarnings
{
    /**
     * @param list<array<string, mixed>> $entries قيود الحصص: اكتساب وخصم
     * @param list<array<string, mixed>> $adjustments المنح والجوائز والخصومات المعتمدة
     */
    public function __construct(
        public string $periodId,
        public int $year,
        public int $month,
        public string $status,
        public string $currency,
        public int $earningsMinorUnits,
        public int $deductionsMinorUnits,
        public int $adjustmentsMinorUnits,
        public int $netMinorUnits,
        public int $sessionsCount,
        public array $entries,
        public array $adjustments,
    ) {}
}

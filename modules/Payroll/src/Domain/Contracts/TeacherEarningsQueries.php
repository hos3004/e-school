<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Contracts;

use Modules\Payroll\Domain\ValueObjects\TeacherPeriodEarnings;

/**
 * القناة المعلنة لقراءة كشف أجر معلم من خارج موديول Payroll.
 *
 * Payroll موديول مختوم (`config/modules.php` → `sealed_domains`)، فلا يقرأ
 * أحد جداوله ولا يستورد نماذجه. بوابة المعلم تمرّ من هنا وحدها.
 *
 * القراءة فقط: لا يعرّض هذا العقد أي طريقة تنشئ قيدة أو تعدّلها، لأن
 * الدفتر append-only ولا يُكتب إلا من إجراءات الموديول نفسه.
 */
interface TeacherEarningsQueries
{
    /**
     * كشوف المعلم مرتَّبة من الأحدث، بحد أقصى `$limit` فترة.
     *
     * @return list<TeacherPeriodEarnings>
     */
    public function periodsFor(
        string $organizationId,
        string $staffProfileId,
        int $limit = 12,
    ): array;
}

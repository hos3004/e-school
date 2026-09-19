<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Payroll\Domain\ValueObjects\TeacherDuesStatement;

interface TeacherDuesQueries
{
    /** @return list<array<string, mixed>> */
    public function periods(Authenticatable $actor): array;

    public function statement(Authenticatable $actor, string $periodId): TeacherDuesStatement;

    /**
     * هل تقبل فترة الرواتب التي يقع فيها هذا التاريخ قيودًا جديدة؟
     *
     * يحتاجه من يوشك أن يُقفل حصة: القيدة تُنسب إلى **شهر الحصة**، وإذا كان
     * مقفلًا يرفض `RecordPayrollEntryAction` القيدة بينما الحصة تُقفل بنجاح —
     * والمستمع يبتلع الرفض في السجل فقط حتى لا يُفشل إقفال الحصة. النتيجة حصة
     * نهائية بلا مستحق لا يعرف بها أحد، ولا تُصحَّح إلا بتسوية يدوية. السؤال
     * هنا يسبق القرار فيمنع هذه الحالة بدل اكتشافها لاحقًا من الدفتر.
     *
     * فترة غير موجودة بعد تُعدّ مقبولة: `PayrollPeriodResolver` يفتحها وقت
     * تسجيل أول قيدة.
     */
    public function acceptsEntriesOn(string $organizationId, CarbonImmutable $date): bool;
}

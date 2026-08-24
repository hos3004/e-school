<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Shared\Support\Transaction;

/**
 * إيجاد فترة الرواتب التي تنتمي إليها حصة بتاريخها، أو فتحها إن لم تكن موجودة.
 *
 * القيدة تُنسب إلى **شهر الحصة** لا إلى شهر تسجيلها. حصة أُقيمت في مارس
 * واعتُمدت في أبريل تدخل فترة مارس ما دامت مفتوحة، فلا يهاجر مستحق بين
 * الفترات لمجرد تأخر الاعتماد. أما إذا كانت فترة مارس مقفلة فإن
 * `RecordPayrollEntryAction` يرفض القيدة، والتصحيح يكون بقيدة تسوية في
 * الفترة التالية كما ينص عقد الدفتر.
 */
final readonly class PayrollPeriodResolver
{
    public function __construct(private Transaction $transaction) {}

    public function forDate(string $organizationId, CarbonImmutable $date): PayrollPeriod
    {
        $date = $date->utc();
        $year = (int) $date->year;
        $month = (int) $date->month;

        $existing = $this->find($organizationId, $year, $month);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return $this->transaction->run(
                fn (): PayrollPeriod => PayrollPeriod::query()->create([
                    'organization_id' => $organizationId,
                    'year' => $year,
                    'month' => $month,
                    'starts_on' => $date->startOfMonth()->toDateString(),
                    'ends_on' => $date->endOfMonth()->toDateString(),
                    'status' => PayrollPeriodStatus::Open,
                    'totals' => [],
                ]),
            );
        } catch (UniqueConstraintViolationException) {
            /*
             * حصتان تُعتمدان في نفس اللحظة قد تتسابقان على فتح نفس الفترة؛
             * القيد الفريد (organization, year, month) يحسم السباق، والخاسر
             * يقرأ الصف الذي أنشأه الرابح بدل أن يفشل.
             */
            $period = $this->find($organizationId, $year, $month);

            if ($period === null) {
                throw new \RuntimeException(
                    'Payroll period could not be resolved after a unique constraint race.',
                );
            }

            return $period;
        }
    }

    private function find(string $organizationId, int $year, int $month): ?PayrollPeriod
    {
        /** @var PayrollPeriod|null $period */
        $period = PayrollPeriod::query()
            ->where('organization_id', $organizationId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        return $period;
    }
}

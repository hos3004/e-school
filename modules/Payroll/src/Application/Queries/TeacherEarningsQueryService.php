<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Queries;

use Modules\Payroll\Domain\Contracts\TeacherEarningsQueries;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\ValueObjects\TeacherPeriodEarnings;

/**
 * بناء كشف أجر المعلم من الدفتر.
 *
 * الصافي يُحسب من القيود لا من عمود مخزَّن: الدفتر هو مصدر الحقيقة، وأي
 * مجموع محفوظ يمكن أن يتخلّف عنه. الأعداد كلها بالوحدة الصغرى (int) — لا
 * float في أي خطوة.
 *
 * لا تظهر للمعلم إلا التسويات **المعتمدة**: المقترحة قيد نظر الإشراف،
 * وإظهارها تعِد بمبلغ قد لا يُقَر.
 */
final readonly class TeacherEarningsQueryService implements TeacherEarningsQueries
{
    /**
     * @return list<TeacherPeriodEarnings>
     */
    public function periodsFor(
        string $organizationId,
        string $staffProfileId,
        int $limit = 12,
    ): array {
        $entries = PayrollEntry::query()
            ->where('organization_id', $organizationId)
            ->where('staff_profile_id', $staffProfileId)
            ->orderByDesc('created_at')
            ->get();

        if ($entries->isEmpty()) {
            return [];
        }

        $periodIds = $entries->pluck('payroll_period_id')->unique()->values()->all();

        $periods = PayrollPeriod::query()
            ->whereKey($periodIds)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit($limit)
            ->get();

        $adjustments = PayrollAdjustment::query()
            ->where('organization_id', $organizationId)
            ->where('staff_profile_id', $staffProfileId)
            ->whereIn('payroll_period_id', $periods->modelKeys())
            ->whereNotNull('approved_at')
            ->whereNull('rejected_at')
            ->get()
            ->groupBy('payroll_period_id');

        $currency = (string) config('payroll.currency', 'EGP');
        $result = [];

        foreach ($periods as $period) {
            $periodId = (string) $period->getKey();
            $periodEntries = $entries->where('payroll_period_id', $periodId);
            $periodAdjustments = $adjustments->get($periodId) ?? collect();

            $earnings = 0;
            $deductions = 0;
            $sessions = 0;

            foreach ($periodEntries as $entry) {
                $amount = (int) $entry->amount;

                if ($amount < 0) {
                    $deductions += $amount;
                } else {
                    $earnings += $amount;
                }

                if ($entry->session_id !== null) {
                    $sessions++;
                }
            }

            $adjustmentTotal = 0;

            foreach ($periodAdjustments as $adjustment) {
                $adjustmentTotal += (int) $adjustment->amount;
            }

            $result[] = new TeacherPeriodEarnings(
                periodId: $periodId,
                year: (int) $period->year,
                month: (int) $period->month,
                status: $period->status->value,
                currency: $currency,
                earningsMinorUnits: $earnings,
                deductionsMinorUnits: $deductions,
                adjustmentsMinorUnits: $adjustmentTotal,
                netMinorUnits: $earnings + $deductions + $adjustmentTotal,
                sessionsCount: $sessions,
                entries: $periodEntries
                    ->map(static fn (PayrollEntry $entry): array => [
                        'id' => (string) $entry->getKey(),
                        'sessionId' => $entry->session_id === null ? null : (string) $entry->session_id,
                        'entryType' => (string) $entry->entry_type,
                        'outcomeKey' => (string) $entry->outcome_key,
                        'amountMinorUnits' => (int) $entry->amount,
                        'status' => $entry->status->value,
                        'recordedAt' => $entry->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                adjustments: $periodAdjustments
                    ->map(static fn (PayrollAdjustment $adjustment): array => [
                        'id' => (string) $adjustment->getKey(),
                        'type' => (string) $adjustment->type,
                        'amountMinorUnits' => (int) $adjustment->amount,
                        'reason' => (string) $adjustment->reason,
                        'approvedAt' => $adjustment->approved_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            );
        }

        return $result;
    }
}

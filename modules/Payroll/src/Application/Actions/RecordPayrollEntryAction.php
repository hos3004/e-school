<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Modules\Payroll\Domain\Events\PayrollEntryRecorded;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Shared\ValueObjects\Money;
use Shared\ValueObjects\TimeRange;

/**
 * تسجيل قيدة جديدة في دفتر المستحقات.
 *
 * الدفتر append-only: هذه العملية تُنشئ فقط — لا تعديل ولا حذف إطلاقًا.
 * كل قيدة تحمل rate_snapshot بالسعر والقاعدة المطبَّقة ووقت الحصة،
 * فلا يؤثر تغيير سعر المعلم غدًا على قيدة أمس.
 */
final readonly class RecordPayrollEntryAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed>|null $description
     */
    public function execute(
        string $organizationId,
        string $payrollPeriodId,
        string $staffProfileId,
        string $teacherContractId,
        string $entryType,
        string $outcomeKey,
        Money $amount,
        TimeRange $sessionTime,
        ?string $sessionId = null,
        ?string $deferredUntilSessionId = null,
        ?string $resolvedVia = null,
        ?array $description = null,
        ?string $actorId = null,
    ): PayrollEntry {
        $outcomes = config('payroll.outcomes');

        if (!is_array($outcomes) || !array_key_exists($outcomeKey, $outcomes)) {
            throw BusinessRuleViolation::make(
                'payroll.entry.unknown_outcome',
                'payroll::actions.record_entry.unknown_outcome',
                ['outcome' => $outcomeKey],
            );
        }

        if ($amount->currency !== (string) config('payroll.currency')) {
            throw BusinessRuleViolation::make(
                'payroll.entry.currency_mismatch',
                'payroll::actions.record_entry.currency_mismatch',
                ['currency' => $amount->currency],
            );
        }

        /** @var PayrollPeriod|null $period */
        $period = PayrollPeriod::query()
            ->where('organization_id', $organizationId)
            ->whereKey($payrollPeriodId)
            ->first();

        if ($period === null) {
            throw BusinessRuleViolation::make(
                'payroll.period.not_found',
                'payroll::actions.record_entry.period_not_found',
            );
        }

        if (!$period->status->acceptsEntries()) {
            throw BusinessRuleViolation::make(
                'payroll.period.closed',
                'payroll::actions.record_entry.period_closed',
                ['status' => $period->status->value],
            );
        }

        $entry = $this->transaction->run(function () use (
            $organizationId,
            $payrollPeriodId,
            $staffProfileId,
            $teacherContractId,
            $entryType,
            $outcomeKey,
            $amount,
            $sessionTime,
            $sessionId,
            $deferredUntilSessionId,
            $resolvedVia,
            $description,
            $actorId,
        ): PayrollEntry {
            try {
                return PayrollEntry::query()->create([
                    'organization_id' => $organizationId,
                    'payroll_period_id' => $payrollPeriodId,
                    'staff_profile_id' => $staffProfileId,
                    'session_id' => $sessionId,
                    'teacher_contract_id' => $teacherContractId,
                    'entry_type' => $entryType,
                    'outcome_key' => $outcomeKey,
                    'amount' => $amount->minorUnits,
                    'currency' => $amount->currency,
                    'rate_snapshot' => [
                        'amount_minor_units' => $amount->minorUnits,
                        'currency' => $amount->currency,
                        'resolved_via' => $resolvedVia,
                        'session_time' => $sessionTime->toArray(),
                        'captured_at' => now('UTC')->toIso8601String(),
                        'captured_by' => $actorId,
                    ],
                    'status' => $deferredUntilSessionId === null
                        ? PayrollEntryStatus::Recorded
                        : PayrollEntryStatus::Deferred,
                    'deferred_until_session_id' => $deferredUntilSessionId,
                    'description' => $description,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw BusinessRuleViolation::make(
                    'payroll.entry.duplicate',
                    'payroll::actions.record_entry.duplicate',
                    ['session_id' => (string) $sessionId, 'staff_profile_id' => $staffProfileId],
                );
            }
        });

        $this->events->dispatch(new PayrollEntryRecorded(
            entryId: $entry->id,
            organizationId: $entry->organization_id,
            payrollPeriodId: $entry->payroll_period_id,
            staffProfileId: $entry->staff_profile_id,
            sessionId: $entry->session_id,
            entryType: $entry->entry_type,
            outcomeKey: $entry->outcome_key,
            amountMinorUnits: $amount->minorUnits,
            currency: $amount->currency,
        ));

        return $entry;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Payroll\Application\Queries;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Domain\Contracts\TeacherDuesQueries;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Modules\Payroll\Domain\Models\PayrollAdjustment;
use Modules\Payroll\Domain\Models\PayrollEntry;
use Modules\Payroll\Domain\Models\PayrollPeriod;
use Modules\Payroll\Domain\ValueObjects\TeacherDuesMoney;
use Modules\Payroll\Domain\ValueObjects\TeacherDuesStatement;

final readonly class TeacherDuesQueryService implements TeacherDuesQueries
{
    public function periods(Authenticatable $actor): array
    {
        Gate::forUser($actor)->authorize('viewAny', PayrollPeriod::class);

        return PayrollPeriod::query()->forOrganization((string) $actor->getAttribute('organization_id'))
            ->orderByDesc('year')->orderByDesc('month')->get()
            ->map(fn (PayrollPeriod $period): array => $this->periodData($period))->all();
    }

    public function statement(Authenticatable $actor, string $periodId): TeacherDuesStatement
    {
        $org = (string) $actor->getAttribute('organization_id');
        $period = PayrollPeriod::query()->forOrganization($org)->findOrFail($periodId);
        Gate::forUser($actor)->authorize('view', $period);
        $entries = PayrollEntry::query()->forOrganization($org)->where('payroll_period_id', $periodId)
            ->orderBy('created_at')->orderBy('id')->get();
        $adjustments = PayrollAdjustment::query()->forOrganization($org)->where('payroll_period_id', $periodId)
            ->orderByDesc('proposed_at')->orderBy('id')->get();

        return new TeacherDuesStatement($this->periodData($period),
            $entries->map(static fn (PayrollEntry $entry): array => [
                'id' => $entry->id, 'staffProfileId' => $entry->staff_profile_id, 'sessionId' => $entry->session_id,
                'entryType' => $entry->entry_type, 'outcomeKey' => $entry->outcome_key, 'status' => $entry->status->value,
                'amountMinorUnits' => $entry->amount, 'amount' => TeacherDuesMoney::display($entry->amount),
                'currency' => $entry->currency, 'recordedAt' => $entry->created_at?->toIso8601String() ?? ($entry->rate_snapshot['captured_at'] ?? null),
                'rateSnapshot' => $entry->rate_snapshot,
                'snapshotAmount' => isset($entry->rate_snapshot['amount_minor_units'])
                    ? TeacherDuesMoney::display((int) $entry->rate_snapshot['amount_minor_units']) : null,
            ])->all(),
            $adjustments->map(static fn (PayrollAdjustment $item): array => [
                'id' => $item->id, 'staffProfileId' => $item->staff_profile_id, 'type' => $item->type,
                'amountMinorUnits' => $item->amount, 'amount' => TeacherDuesMoney::display($item->amount), 'currency' => $item->currency,
                'reason' => $item->reason, 'referencePeriodId' => $item->references_period_id,
                'status' => $item->approved_at !== null ? 'approved' : ($item->rejected_at !== null ? 'rejected' : 'pending'),
                'proposedBy' => $item->proposed_by, 'proposedAt' => $item->proposed_at->toIso8601String(),
                'approvedBy' => $item->approved_by, 'approvedAt' => $item->approved_at?->toIso8601String(),
                'rejectedAt' => $item->rejected_at?->toIso8601String(), 'rejectionReason' => $item->rejection_reason,
                'canApprove' => $period->status->acceptsAdjustments() && Gate::forUser($actor)->allows('approve', $item),
                'canReject' => $period->status->acceptsAdjustments() && Gate::forUser($actor)->allows('reject', $item),
            ])->all(), $this->summaries($period, $entries, $adjustments));
    }

    /** @param Collection<int, PayrollEntry> $entries
     * @param Collection<int, PayrollAdjustment> $adjustments
     * @return list<array<string, mixed>>
     */
    private function summaries(PayrollPeriod $period, Collection $entries, Collection $adjustments): array
    {
        $sums = [];
        $blank = ['entryEarnings' => 0, 'entryDeductions' => 0, 'bonus' => 0, 'adjustments' => 0, 'deferred' => 0, 'pendingAdjustments' => 0];
        foreach ($entries as $entry) {
            $sums[$entry->staff_profile_id][$entry->currency] ??= $blank;
            $key = $entry->status === PayrollEntryStatus::Deferred ? 'deferred' : ($entry->amount < 0 ? 'entryDeductions' : 'entryEarnings');
            $sums[$entry->staff_profile_id][$entry->currency][$key] += $entry->amount;
        }
        foreach ($adjustments as $item) {
            $sums[$item->staff_profile_id][$item->currency] ??= $blank;
            if ($item->rejected_at !== null) {
                continue;
            }
            $key = $item->approved_at === null ? 'pendingAdjustments' : ($item->type === 'bonus' ? 'bonus' : 'adjustments');
            $sums[$item->staff_profile_id][$item->currency][$key] += $item->amount;
        }
        $result = [];
        foreach ($sums as $staffId => $currencies) {
            $totals = [];
            foreach ($currencies as $currency => $values) {
                $values['entryNet'] = $values['entryEarnings'] + $values['entryDeductions'];
                $values['net'] = $values['entryNet'] + $values['bonus'] + $values['adjustments'];
                $values['paid'] = $period->status->isFrozen() ? $values['net'] : 0;
                $values['remaining'] = $values['net'] - $values['paid'];
                $totals[] = ['currency' => $currency,
                    'amounts' => array_map(static fn (int $minor): string => TeacherDuesMoney::display($minor), $values),
                    'minorUnits' => array_map(static fn (int $minor): string => (string) $minor, $values),
                ];
            }
            $result[] = ['staffProfileId' => $staffId, 'totals' => $totals];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function periodData(PayrollPeriod $period): array
    {
        return ['id' => $period->id, 'year' => $period->year, 'month' => $period->month,
            'startsOn' => $period->starts_on->toDateString(), 'endsOn' => $period->ends_on->toDateString(),
            'status' => $period->status->value, 'canAdjust' => $period->status->acceptsAdjustments(),
            'frozen' => $period->status->isFrozen(), 'paidAt' => $period->paid_at?->toIso8601String(),
            'lockedAt' => $period->locked_at?->toIso8601String(), 'approvedAt' => $period->approved_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Events\TeacherContractCreated;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherContract;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\Money;

final readonly class CreateTeacherContract
{
    public function execute(
        StaffProfile $profile,
        ContractBasis $basis,
        CarbonImmutable|string $effectiveFrom,
        ?CarbonImmutable|string $effectiveTo = null,
        ?Money $baseAmount = null,
        ?int $monthlyTargetSessions = null,
        ?int $targetAdminTasks = null,
        ?int $targetTrainingSessions = null,
        ?array $terms = null,
    ): TeacherContract {
        $from = $effectiveFrom instanceof CarbonImmutable ? $effectiveFrom : CarbonImmutable::parse($effectiveFrom);
        $to = $effectiveTo === null ? null : ($effectiveTo instanceof CarbonImmutable ? $effectiveTo : CarbonImmutable::parse($effectiveTo));

        if ($to !== null && ! $to->gt($from)) {
            throw BusinessRuleViolation::make(
                'staff.contract_period_invalid',
                'staff::errors.contract_period_invalid',
                ['effective_from' => $from->toDateString(), 'effective_to' => $to->toDateString()],
            );
        }

        if ($basis->requiresRates() && $baseAmount !== null) {
            throw BusinessRuleViolation::make(
                'staff.contract_base_not_allowed',
                'staff::errors.contract_base_not_allowed',
                ['basis' => $basis->value],
            );
        }

        if (! $basis->requiresRates() && $baseAmount === null) {
            throw BusinessRuleViolation::make(
                'staff.contract_base_required',
                'staff::errors.contract_base_required',
                ['basis' => $basis->value],
            );
        }

        if ($baseAmount !== null && $baseAmount->isNegative()) {
            throw BusinessRuleViolation::make(
                'staff.contract_base_negative',
                'staff::errors.amount_negative',
                [],
            );
        }

        $overlaps = TeacherContract::query()
            ->forProfile($profile->id)
            ->whereDate('effective_from', '<', $to ?? $from->addDay())
            ->where(
                fn (\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder => $q
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>', $from),
            )
            ->exists();

        if ($overlaps) {
            throw BusinessRuleViolation::make(
                'staff.contract_overlaps',
                'staff::errors.contract_overlaps',
                ['staff_code' => $profile->staff_code],
            );
        }

        $contract = DB::transaction(function () use ($profile, $basis, $from, $to, $baseAmount, $monthlyTargetSessions, $targetAdminTasks, $targetTrainingSessions, $terms): TeacherContract {
            return TeacherContract::query()->create([
                'organization_id' => $profile->organization_id,
                'staff_profile_id' => $profile->id,
                'basis' => $basis,
                'effective_from' => $from,
                'effective_to' => $to,
                'base_amount' => $baseAmount?->minorUnits,
                'currency' => $baseAmount?->currency,
                'monthly_target_sessions' => $monthlyTargetSessions,
                'target_admin_tasks' => $targetAdminTasks,
                'target_training_sessions' => $targetTrainingSessions,
                'terms' => $terms,
            ]);
        });

        Event::dispatch(new TeacherContractCreated(
            contractId: $contract->id,
            organizationId: $contract->organization_id,
            staffProfileId: $contract->staff_profile_id,
            basis: $contract->basis,
            baseAmount: $contract->baseMoney(),
            effectiveFrom: $from->toDateString(),
            effectiveTo: $to?->toDateString(),
        ));

        return $contract;
    }
}

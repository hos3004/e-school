<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Events\TeacherRateCreated;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Domain\Models\TeacherRate;
use Shared\Support\BusinessRuleViolation;
use Shared\ValueObjects\Money;

final readonly class AddTeacherRate
{
    public function execute(
        TeacherContract $contract,
        RateScope $scope,
        Money $amount,
        CarbonImmutable|string $effectiveFrom,
        CarbonImmutable|string|null $effectiveTo = null,
        ?string $programId = null,
        ?string $courseId = null,
        ?string $sessionType = null,
    ): TeacherRate {
        if ($amount->isZero() || $amount->isNegative()) {
            throw BusinessRuleViolation::make(
                'staff.rate_amount_invalid',
                'staff::errors.amount_negative',
                [],
            );
        }

        if ($scope->requiresProgram() && $programId === null) {
            throw BusinessRuleViolation::make(
                'staff.rate_scope_program_required',
                'staff::errors.rate_scope_program_required',
                ['scope' => $scope->value],
            );
        }

        if ($scope->requiresCourse() && $courseId === null) {
            throw BusinessRuleViolation::make(
                'staff.rate_scope_course_required',
                'staff::errors.rate_scope_course_required',
                ['scope' => $scope->value],
            );
        }

        $from = $effectiveFrom instanceof CarbonImmutable ? $effectiveFrom : CarbonImmutable::parse($effectiveFrom);
        $to = $effectiveTo === null ? null : ($effectiveTo instanceof CarbonImmutable ? $effectiveTo : CarbonImmutable::parse($effectiveTo));

        if ($to !== null && !$to->gt($from)) {
            throw BusinessRuleViolation::make(
                'staff.rate_period_invalid',
                'staff::errors.contract_period_invalid',
                ['effective_from' => $from->toDateString(), 'effective_to' => $to->toDateString()],
            );
        }

        // السعر الساري بتاريخ الحصة هو ما يُعتمد — لذلك لا يجوز أن يتقاطع سعران
        // بنفس النطاق على نفس العقد في أي فترة.
        $overlaps = TeacherRate::query()
            ->forContract($contract->id)
            ->where('scope', $scope)
            ->whereDate('effective_from', '<', $to ?? $from->addDay())
            ->where(
                fn (Builder $q): Builder => $q
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>', $from),
            )
            ->when($programId !== null, fn (Builder $q): Builder => $q->where('program_id', $programId))
            ->when($courseId !== null, fn (Builder $q): Builder => $q->where('course_id', $courseId))
            ->when($sessionType !== null, fn (Builder $q): Builder => $q->where('session_type', $sessionType))
            ->exists();

        if ($overlaps) {
            throw BusinessRuleViolation::make(
                'staff.rate_overlaps',
                'staff::errors.rate_overlaps',
                ['scope' => $scope->label()],
            );
        }

        $rate = DB::transaction(function () use ($contract, $scope, $amount, $from, $to, $programId, $courseId, $sessionType): TeacherRate {
            return TeacherRate::query()->create([
                'teacher_contract_id' => $contract->id,
                'scope' => $scope,
                'program_id' => $programId,
                'course_id' => $courseId,
                'session_type' => $sessionType,
                'amount' => $amount->minorUnits,
                'currency' => $amount->currency,
                'effective_from' => $from,
                'effective_to' => $to,
            ]);
        });

        Event::dispatch(new TeacherRateCreated(
            rateId: $rate->id,
            contractId: $contract->id,
            staffProfileId: $contract->staff_profile_id,
            scope: $rate->scope,
            programId: $rate->program_id,
            courseId: $rate->course_id,
            sessionType: $rate->session_type,
            amount: $rate->money(),
            effectiveFrom: $from->toDateString(),
            effectiveTo: $to?->toDateString(),
        ));

        return $rate;
    }
}

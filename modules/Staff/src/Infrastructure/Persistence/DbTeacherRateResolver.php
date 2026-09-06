<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Staff\Domain\Contracts\TeacherRateResolver;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Domain\Models\TeacherRate;
use Shared\ValueObjects\Money;

final readonly class DbTeacherRateResolver implements TeacherRateResolver
{
    public function resolve(
        string $staffProfileId,
        CarbonImmutable $sessionDate,
        ?string $programId = null,
        ?string $courseId = null,
        ?string $sessionType = null,
    ): ?array {
        $contract = TeacherContract::query()
            ->forProfile($staffProfileId)
            ->activeOn($sessionDate)
            ->orderByDesc('effective_from')
            ->first();

        if ($contract === null) {
            return null;
        }

        /** @var list<array{scope: RateScope, query: callable(Builder<TeacherRate>): Builder<TeacherRate>}> $candidates */
        $candidates = [
            [
                'scope' => RateScope::Course,
                'query' => fn (Builder $q): Builder => $q
                    ->where('program_id', $programId)
                    ->where('course_id', $courseId),
            ],
            [
                'scope' => RateScope::Program,
                'query' => fn (Builder $q): Builder => $q->where('program_id', $programId),
            ],
            [
                'scope' => RateScope::SessionType,
                'query' => fn (Builder $q): Builder => $q->where('session_type', $sessionType),
            ],
            [
                'scope' => RateScope::Default,
                'query' => fn (Builder $q): Builder => $q
                    ->whereNull('program_id')
                    ->whereNull('course_id')
                    ->whereNull('session_type'),
            ],
        ];

        foreach ($candidates as $candidate) {
            if ($candidate['scope'] === RateScope::Course && ($courseId === null || $programId === null)) {
                continue;
            }

            if ($candidate['scope'] === RateScope::Program && $programId === null) {
                continue;
            }

            if ($candidate['scope'] === RateScope::SessionType && $sessionType === null) {
                continue;
            }

            /** @var TeacherRate|null $rate */
            $rate = $candidate['query'](
                TeacherRate::query()
                    ->forContract($contract->id)
                    ->where('scope', $candidate['scope'])
                    ->activeOn($sessionDate),
            )
                ->orderByDesc('effective_from')
                ->first();

            if ($rate !== null) {
                return [
                    'money' => $rate->money(),
                    'scope' => $rate->scope,
                    'rate_id' => $rate->id,
                    'contract_id' => $contract->id,
                    'contract_basis' => $contract->basis->value,
                ];
            }
        }

        return null;
    }

    public function resolveDeduction(
        string $staffProfileId,
        CarbonImmutable $sessionDate,
        ?string $programId = null,
        ?string $courseId = null,
        ?string $sessionType = null,
    ): ?array {
        $rate = $this->resolve(
            $staffProfileId,
            $sessionDate,
            $programId,
            $courseId,
            $sessionType,
        );

        if ($rate !== null) {
            return $rate;
        }

        if (config('payroll.salary_session_value.enabled', true) !== true) {
            return null;
        }

        $contract = TeacherContract::query()
            ->forProfile($staffProfileId)
            ->activeOn($sessionDate)
            ->orderByDesc('effective_from')
            ->first();

        if ($contract === null
            || !$contract->basis->requiresBaseAmount()
            || $contract->base_amount === null
            || $contract->monthly_target_sessions === null
            || $contract->monthly_target_sessions <= 0) {
            return null;
        }

        $target = $contract->monthly_target_sessions;
        $minorUnits = intdiv($contract->base_amount, $target);
        $remainder = $contract->base_amount % $target;
        $roundingThreshold = intdiv($target, 2) + ($target % 2);

        // Half-up rounding using integer minor units, including amounts beyond float precision.
        if (abs($remainder) >= $roundingThreshold) {
            $minorUnits += $remainder <=> 0;
        }

        return [
            'money' => Money::of($minorUnits, $contract->currency ?? 'EGP'),
            'scope' => RateScope::Default,
            'rate_id' => (string) $contract->getKey(),
            'contract_id' => (string) $contract->getKey(),
            'contract_basis' => $contract->basis->value,
        ];
    }
}

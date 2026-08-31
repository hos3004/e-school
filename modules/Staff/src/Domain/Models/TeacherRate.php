<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Staff\Domain\Enums\RateScope;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;
use Shared\ValueObjects\Money;

/**
 * @property string $id
 * @property string $teacher_contract_id
 * @property RateScope $scope
 * @property string|null $program_id
 * @property string|null $course_id
 * @property string|null $session_type
 * @property int $amount
 * @property string $currency
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 */
final class TeacherRate extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'teacher_rates';

    protected $fillable = [
        'teacher_contract_id',
        'scope',
        'program_id',
        'course_id',
        'session_type',
        'amount',
        'currency',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'scope' => RateScope::class,
            'amount' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
        ];
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    public function isActiveOn(CarbonImmutable|string $date): bool
    {
        $date = $date instanceof CarbonImmutable ? $date->startOfDay() : CarbonImmutable::parse($date)->startOfDay();
        $from = CarbonImmutable::instance($this->effective_from)->startOfDay();

        if ($date->lt($from)) {
            return false;
        }

        if ($this->effective_to === null) {
            return true;
        }

        return $date->lt(CarbonImmutable::instance($this->effective_to)->startOfDay());
    }

    /**
     * السعر الساري بتاريخ الحصة — لا السعر الحالي.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActiveOn(Builder $query, CarbonImmutable|string $date): Builder
    {
        $date = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(
                fn (Builder $q): Builder => $q
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>', $date),
            );
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForContract(Builder $query, string $contractId): Builder
    {
        return $query->where('teacher_contract_id', $contractId);
    }
}

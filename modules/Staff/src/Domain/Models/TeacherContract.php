<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Staff\Domain\Enums\ContractBasis;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;
use Shared\ValueObjects\Money;

final class TeacherContract extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'teacher_contracts';

    protected $fillable = [
        'organization_id',
        'staff_profile_id',
        'basis',
        'effective_from',
        'effective_to',
        'base_amount',
        'currency',
        'monthly_target_sessions',
        'target_admin_tasks',
        'target_training_sessions',
        'terms',
    ];

    protected function casts(): array
    {
        return [
            'basis' => ContractBasis::class,
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'base_amount' => 'integer',
            'monthly_target_sessions' => 'integer',
            'target_admin_tasks' => 'integer',
            'target_training_sessions' => 'integer',
            'terms' => 'array',
        ];
    }

    public function baseMoney(): ?Money
    {
        if ($this->base_amount === null) {
            return null;
        }

        return Money::of($this->base_amount, $this->currency ?? 'EGP');
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

        return $date->lte(CarbonImmutable::instance($this->effective_to)->endOfDay());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * العقود السارية بتاريخ معيّن.
     *
     * @param  Builder<self>  $query
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
                    ->orWhereDate('effective_to', '>=', $date),
            );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForProfile(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }
}

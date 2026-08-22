<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Payroll\Domain\Enums\PayrollPeriodStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property int $year
 * @property int $month
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property PayrollPeriodStatus $status
 * @property CarbonImmutable|null $calculated_at
 * @property string|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $locked_at
 * @property array<string, mixed>|null $totals
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PayrollEntry> $entries
 */
final class PayrollPeriod extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'payroll_periods';

    protected $fillable = [
        'organization_id',
        'year',
        'month',
        'starts_on',
        'ends_on',
        'status',
        'calculated_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'paid_at',
        'locked_at',
        'totals',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'int',
            'month' => 'int',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'status' => PayrollPeriodStatus::class,
            'calculated_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'totals' => 'array',
        ];
    }

    /** @return HasMany<PayrollEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeLocked(Builder $query): Builder
    {
        return $query->whereNotNull('locked_at');
    }
}

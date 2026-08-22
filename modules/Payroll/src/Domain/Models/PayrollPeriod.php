<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->whereNotNull('locked_at');
    }
}

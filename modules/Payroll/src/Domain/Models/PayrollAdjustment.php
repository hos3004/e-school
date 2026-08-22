<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $payroll_period_id
 * @property string $staff_profile_id
 * @property string $type
 * @property int $amount
 * @property string $currency
 * @property string $reason
 * @property string|null $references_period_id
 * @property string $proposed_by
 * @property CarbonImmutable $proposed_at
 * @property string|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $rejected_by
 * @property CarbonImmutable|null $rejected_at
 * @property string|null $rejection_reason
 */
final class PayrollAdjustment extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'payroll_adjustments';

    protected $fillable = [
        'organization_id',
        'payroll_period_id',
        'staff_profile_id',
        'type',
        'amount',
        'currency',
        'reason',
        'references_period_id',
        'proposed_by',
        'proposed_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'int',
            'proposed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
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
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('approved_at')->whereNull('rejected_at');
    }
}

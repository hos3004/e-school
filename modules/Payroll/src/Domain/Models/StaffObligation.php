<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $staff_profile_id
 * @property string $payroll_period_id
 * @property string $obligation_type
 * @property int $amount
 * @property string $currency
 * @property int $target_teaching
 * @property int $achieved_teaching
 * @property int $target_admin
 * @property int $achieved_admin
 * @property int $target_training
 * @property int $achieved_training
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class StaffObligation extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'staff_obligations';

    protected $fillable = [
        'organization_id',
        'staff_profile_id',
        'payroll_period_id',
        'obligation_type',
        'amount',
        'currency',
        'target_teaching',
        'achieved_teaching',
        'target_admin',
        'achieved_admin',
        'target_training',
        'achieved_training',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'int',
            'target_teaching' => 'int',
            'achieved_teaching' => 'int',
            'target_admin' => 'int',
            'achieved_admin' => 'int',
            'target_training' => 'int',
            'achieved_training' => 'int',
            'status' => 'string',
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
    public function scopeForStaff(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }
}

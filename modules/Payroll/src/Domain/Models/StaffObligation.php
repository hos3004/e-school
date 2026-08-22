<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForStaff(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }
}

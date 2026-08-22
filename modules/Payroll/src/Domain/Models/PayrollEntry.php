<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class PayrollEntry extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'payroll_entries';

    protected $fillable = [
        'organization_id',
        'payroll_period_id',
        'staff_profile_id',
        'session_id',
        'teacher_contract_id',
        'entry_type',
        'outcome_key',
        'amount',
        'currency',
        'rate_snapshot',
        'status',
        'deferred_until_session_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'int',
            'rate_snapshot' => 'array',
            'status' => PayrollEntryStatus::class,
            'description' => 'array',
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

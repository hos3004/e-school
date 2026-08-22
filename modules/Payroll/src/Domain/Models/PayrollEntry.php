<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Domain\Enums\PayrollEntryStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $payroll_period_id
 * @property string $staff_profile_id
 * @property string|null $session_id
 * @property string|null $teacher_contract_id
 * @property string $entry_type
 * @property string $outcome_key
 * @property int $amount
 * @property string $currency
 * @property array<string, mixed> $rate_snapshot
 * @property PayrollEntryStatus $status
 * @property string|null $deferred_until_session_id
 * @property array<string, mixed>|null $description
 */
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

<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class MonthlyReport extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'monthly_reports';

    protected $fillable = [
        'organization_id',
        'student_profile_id',
        'enrollment_id',
        'period_year',
        'period_month',
        'metrics',
        'supervisor_summary',
        'status',
        'approved_by',
        'approved_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'int',
            'period_month' => 'int',
            'metrics' => 'array',
            'approved_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'status' => 'string',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}

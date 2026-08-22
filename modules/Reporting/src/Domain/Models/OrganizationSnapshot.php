<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Reporting\Domain\Enums\SnapshotType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * لقطة تنظيمية يومية/أسبوعية/شهرية — Read Model مجمّع لكل مؤسسة.
 *
 * تُكتب بمفتاح فريد (مؤسسة، تاريخ، نوع فترة) فتُحدَّث idempotent.
 */
final class OrganizationSnapshot extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'report_organization_snapshots';

    protected $fillable = [
        'organization_id',
        'snapshot_date',
        'period_type',
        'students_active',
        'students_frozen',
        'teachers_active',
        'sessions_held',
        'sessions_cancelled',
        'attendance_rate_bp',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'immutable_date',
            'period_type' => SnapshotType::class,
            'students_active' => 'integer',
            'students_frozen' => 'integer',
            'teachers_active' => 'integer',
            'sessions_held' => 'integer',
            'sessions_cancelled' => 'integer',
            'attendance_rate_bp' => 'integer',
        ];
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('snapshot_date', $date);
    }
}

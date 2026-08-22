<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Reporting\Domain\Enums\SnapshotType;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * لقطة تنظيمية يومية/أسبوعية/شهرية — Read Model مجمّع لكل مؤسسة.
 *
 * تُكتب بمفتاح فريد (مؤسسة، تاريخ، نوع فترة) فتُحدَّث idempotent.
 *
 * @property string $id
 * @property string $organization_id
 * @property CarbonImmutable $snapshot_date
 * @property SnapshotType $period_type
 * @property int $students_active
 * @property int $students_frozen
 * @property int $teachers_active
 * @property int $sessions_held
 * @property int $sessions_cancelled
 * @property int $attendance_rate_bp
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('snapshot_date', $date);
    }
}

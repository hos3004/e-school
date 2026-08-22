<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Schedule extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'schedules';

    protected $fillable = [
        'organization_id',
        'group_id',
        'student_profile_id',
        'course_id',
        'staff_profile_id',
        'session_type',
        'rrule',
        'start_time',
        'duration_minutes',
        'timezone',
        'starts_on',
        'ends_on',
        'materialized_until',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'int',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'materialized_until' => 'immutable_date',
            'is_active' => 'bool',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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

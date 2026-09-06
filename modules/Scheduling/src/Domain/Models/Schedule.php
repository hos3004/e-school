<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $group_id
 * @property string|null $student_profile_id
 * @property string $course_id
 * @property string $staff_profile_id
 * @property string $session_type
 * @property string $rrule
 * @property string $start_time
 * @property int $duration_minutes
 * @property string $timezone
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property CarbonImmutable $materialized_until
 * @property bool $is_active
 * @property string $created_by
 */
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

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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

    /** @return HasMany<ScheduleWeeklySlot, $this> */
    public function weeklySlots(): HasMany
    {
        return $this->hasMany(ScheduleWeeklySlot::class)->orderBy('weekday');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Scheduling\Domain\Enums\ScheduleChangeStatus;
use Shared\Concerns\HasUlid;

/**
 * طلب المعلم تغيير الموعد الأسبوعي الدائم لقالب جدول.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $schedule_id
 * @property string $staff_profile_id
 * @property string $requested_by
 * @property ScheduleChangeStatus $status
 * @property list<int> $proposed_weekdays
 * @property list<array{weekday: int, start_time: string}>|null $proposed_weekly_slots
 * @property string $proposed_start_time
 * @property int $proposed_interval_weeks
 * @property string $reason
 * @property CarbonImmutable $expires_at
 * @property string|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $applied_at
 */
final class ScheduleChangeRequest extends Model
{
    use HasUlid;

    protected $table = 'schedule_change_requests';

    protected $fillable = [
        'organization_id',
        'schedule_id',
        'staff_profile_id',
        'requested_by',
        'status',
        'proposed_weekdays',
        'proposed_weekly_slots',
        'proposed_start_time',
        'proposed_interval_weeks',
        'reason',
        'expires_at',
        'decided_by',
        'decided_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScheduleChangeStatus::class,
            'proposed_weekdays' => 'array',
            'proposed_weekly_slots' => 'array',
            'proposed_interval_weeks' => 'int',
            'expires_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /** @return HasMany<ScheduleChangeApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(ScheduleChangeApproval::class, 'schedule_change_request_id');
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
        return $query->where('status', ScheduleChangeStatus::Pending->value);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}

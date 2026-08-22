<?php

declare(strict_types=1);

namespace Modules\Sessions\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Session extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'sessions';

    protected $fillable = [
        'organization_id',
        'schedule_id',
        'group_id',
        'course_id',
        'staff_profile_id',
        'substitute_for_staff_id',
        'makeup_for_session_id',
        'session_type',
        'status',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'title',
        'notes',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'scheduled_start' => 'immutable_datetime',
            'scheduled_end' => 'immutable_datetime',
            'actual_start' => 'immutable_datetime',
            'actual_end' => 'immutable_datetime',
            'title' => 'array',
            'cancelled_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<SessionParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class);
    }

    /**
     * @return HasMany<SessionStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(SessionStatusHistory::class);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForStaff(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    public function scopeWithStatus(Builder $query, SessionStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}

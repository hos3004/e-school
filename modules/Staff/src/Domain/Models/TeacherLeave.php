<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Staff\Domain\Enums\TeacherLeaveStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class TeacherLeave extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public $timestamps = false;

    protected $table = 'teacher_leaves';

    protected $fillable = [
        'staff_profile_id',
        'starts_at',
        'ends_at',
        'reason',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TeacherLeaveStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === TeacherLeaveStatus::Pending;
    }

    /**
     * هل يتقاطع هذا الطلب مع فترة معيّنة؟
     */
    public function overlaps(CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        $starts = CarbonImmutable::instance($this->starts_at);
        $ends = CarbonImmutable::instance($this->ends_at);

        return $starts->lt($endsAt) && $ends->gt($startsAt);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForProfile(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    /**
     * الإجازات التي تتقاطع زمنيًا مع فترة معيّنة.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Builder
    {
        return $query->whereDate('starts_at', '<', $endsAt)->whereDate('ends_at', '>', $startsAt);
    }
}

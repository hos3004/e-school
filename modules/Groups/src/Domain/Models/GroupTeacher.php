<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class GroupTeacher extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'group_teachers';

    protected $fillable = [
        'group_id',
        'staff_profile_id',
        'course_id',
        'role',
        'assigned_from',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'role' => GroupTeacherRole::class,
            'assigned_from' => 'immutable_date',
            'assigned_to' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** هل الإسناد ما زال مفتوحًا (لم يُحدَّد تاريخ انتهائه)؟ */
    public function isOpen(): bool
    {
        return $this->assigned_to === null;
    }

    /** هل الإسناد سارٍ في التاريخ المعطى؟ */
    public function covers(CarbonImmutable $date): bool
    {
        $from = $this->assigned_from;
        $to = $this->assigned_to;

        return $date->greaterThanOrEqualTo($from)
            && ($to === null || $date->lessThanOrEqualTo($to));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForStaff(Builder $query, string $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForGroup(Builder $query, string $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }
}

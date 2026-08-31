<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $group_id
 * @property string $student_profile_id
 * @property MembershipStatus $status
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $left_at
 * @property CarbonImmutable|null $created_at
 * @property-read Group|null $group
 */
final class GroupMembership extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'group_memberships';

    protected $fillable = [
        'group_id',
        'student_profile_id',
        'joined_at',
        'left_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForStudent(Builder $query, string $studentProfileId): Builder
    {
        return $query->where('student_profile_id', $studentProfileId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForGroup(Builder $query, string $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }
}

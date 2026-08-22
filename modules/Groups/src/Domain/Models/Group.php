<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Groups\Domain\Enums\GroupStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class Group extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'groups';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'capacity',
        'timezone',
        'status',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'capacity' => 'int',
            'timezone' => 'string',
            'status' => GroupStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /**
     * @return HasMany<GroupMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    /**
     * @return HasMany<GroupTeacher, $this>
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(GroupTeacher::class);
    }

    /**
     * @return HasMany<GroupProgram, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(GroupProgram::class);
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
    public function scopeWithStatus(Builder $query, GroupStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}

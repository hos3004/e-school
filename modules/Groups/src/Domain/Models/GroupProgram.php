<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class GroupProgram extends Model
{
    use HasModuleFactory;
    use HasUlid;

    public const UPDATED_AT = null;

    protected $table = 'group_programs';

    protected $fillable = [
        'group_id',
        'program_id',
    ];

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
    public function scopeForGroup(Builder $query, string $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForProgram(Builder $query, string $programId): Builder
    {
        return $query->where('program_id', $programId);
    }
}

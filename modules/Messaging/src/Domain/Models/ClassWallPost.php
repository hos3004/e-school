<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $group_id
 * @property string $user_id
 * @property string $body
 * @property array<int, array<string, mixed>>|null $attachments
 * @property bool $is_pinned
 * @property CarbonImmutable $created_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ClassWallComment> $comments
 */
final class ClassWallPost extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'class_wall_posts';

    protected $fillable = [
        'organization_id',
        'group_id',
        'user_id',
        'body',
        'attachments',
        'is_pinned',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_pinned' => 'bool',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ClassWallComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ClassWallComment::class, 'post_id');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}

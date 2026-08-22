<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $post_id
 * @property string $user_id
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property Carbon|null $deleted_at
 * @property-read ClassWallPost $post
 */
final class ClassWallComment extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'class_wall_comments';

    protected $fillable = [
        'organization_id',
        'post_id',
        'user_id',
        'body',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ClassWallPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ClassWallPost::class, 'post_id');
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
    public function scopeForPost(Builder $query, string $postId): Builder
    {
        return $query->where('post_id', $postId);
    }
}

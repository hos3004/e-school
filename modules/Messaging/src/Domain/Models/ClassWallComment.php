<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ClassWallPost::class, 'post_id');
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForPost(Builder $query, string $postId): Builder
    {
        return $query->where('post_id', $postId);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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
        ];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ClassWallComment::class, 'post_id');
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}

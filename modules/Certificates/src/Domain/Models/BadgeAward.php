<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class BadgeAward extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'badge_awards';

    protected $fillable = [
        'organization_id',
        'badge_id',
        'user_id',
        'awarded_by',
        'reason',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'immutable_datetime',
        ];
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Certificates\Domain\Enums\BadgeTier;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property string|null $icon_path
 * @property BadgeTier $tier
 * @property bool $is_active
 */
final class Badge extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'badges';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'icon_path',
        'tier',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'tier' => BadgeTier::class,
            'is_active' => 'bool',
        ];
    }

    public function awards(): HasMany
    {
        return $this->hasMany(BadgeAward::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}

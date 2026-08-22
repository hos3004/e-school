<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

final class IntegrationProvider extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'integration_providers';

    protected $fillable = [
        'key',
        'name',
        'category',
        'driver',
        'is_active',
        'default_settings',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'default_settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<IntegrationConnection, $this>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(IntegrationConnection::class, 'provider_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}

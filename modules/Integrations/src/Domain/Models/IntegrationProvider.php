<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $key
 * @property array<string, string> $name
 * @property string $category
 * @property string|null $driver
 * @property bool $is_active
 * @property array<string, mixed>|null $default_settings
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, IntegrationConnection> $connections
 */
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

    /**
     * @param Builder<IntegrationProvider> $query
     * @return Builder<IntegrationProvider>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder<IntegrationProvider> $query
     * @return Builder<IntegrationProvider>
     */
    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}

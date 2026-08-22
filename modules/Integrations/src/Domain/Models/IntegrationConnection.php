<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $provider_id
 * @property ConnectionStatus $status
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $disabled_at
 * @property CarbonImmutable|null $last_error_at
 * @property string|null $last_error_message
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, IntegrationWebhookDelivery> $webhookDeliveries
 */
final class IntegrationConnection extends Model
{
    use HasModuleFactory;
    use HasUlid;
    use SoftDeletes;

    protected $table = 'integration_connections';

    protected $fillable = [
        'organization_id',
        'provider_id',
        'status',
        'credentials',
        'settings',
        'activated_at',
        'disabled_at',
        'last_error_at',
        'last_error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'activated_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'last_error_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<IntegrationWebhookDelivery, $this>
     */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(IntegrationWebhookDelivery::class, 'connection_id');
    }

    /**
     * @param Builder<IntegrationConnection> $query
     * @return Builder<IntegrationConnection>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * @param Builder<IntegrationConnection> $query
     * @return Builder<IntegrationConnection>
     */
    public function scopeWithStatus(Builder $query, ConnectionStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}

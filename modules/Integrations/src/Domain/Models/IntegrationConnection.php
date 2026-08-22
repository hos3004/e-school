<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

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

    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeWithStatus(Builder $query, ConnectionStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}

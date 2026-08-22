<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Enums\WebhookDirection;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $connection_id
 * @property WebhookDirection $direction
 * @property string $event_type
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property array<string, mixed>|null $payload
 * @property int|null $response_code
 * @property string|null $response_body
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $next_retry_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read IntegrationConnection $connection
 */
final class IntegrationWebhookDelivery extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'integration_webhook_deliveries';

    protected $fillable = [
        'connection_id',
        'direction',
        'event_type',
        'status',
        'attempts',
        'payload',
        'response_code',
        'response_body',
        'delivered_at',
        'next_retry_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => WebhookDirection::class,
            'status' => DeliveryStatus::class,
            'payload' => 'array',
            'response_code' => 'integer',
            'attempts' => 'integer',
            'delivered_at' => 'immutable_datetime',
            'next_retry_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<IntegrationConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    /**
     * @param Builder<IntegrationWebhookDelivery> $query
     * @return Builder<IntegrationWebhookDelivery>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->whereHas('connection', fn (Builder $q): Builder => $q->where('organization_id', $organizationId));
    }

    /**
     * @param Builder<IntegrationWebhookDelivery> $query
     * @return Builder<IntegrationWebhookDelivery>
     */
    public function scopeDueForRetry(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [DeliveryStatus::Retrying, DeliveryStatus::Failed])
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now());
    }
}

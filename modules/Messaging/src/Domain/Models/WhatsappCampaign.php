<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $created_by
 * @property string $name
 * @property string $body
 * @property WhatsappCampaignStatus $status
 * @property string $reason
 * @property int $delay_min_seconds
 * @property int $delay_max_seconds
 * @property int $total_recipients
 * @property int $sent_count
 * @property int $failed_count
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $media_expires_at
 * @property CarbonImmutable|null $media_pruned_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class WhatsappCampaign extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'organization_id',
        'created_by',
        'name',
        'body',
        'status',
        'reason',
        'delay_min_seconds',
        'delay_max_seconds',
        'total_recipients',
        'sent_count',
        'failed_count',
        'started_at',
        'completed_at',
        'media_expires_at',
        'media_pruned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsappCampaignStatus::class,
            'delay_min_seconds' => 'integer',
            'delay_max_seconds' => 'integer',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'media_expires_at' => 'immutable_datetime',
            'media_pruned_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<WhatsappCampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappCampaignRecipient::class, 'campaign_id');
    }

    /** @return HasMany<WhatsappCampaignMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(WhatsappCampaignMedia::class, 'campaign_id')->orderBy('position');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}

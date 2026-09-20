<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $organization_id
 * @property string|null $name
 * @property string $phone_input
 * @property string|null $phone
 * @property WhatsappCampaignRecipientStatus $status
 * @property string|null $failure_reason
 * @property string|null $external_message_id
 * @property CarbonImmutable|null $dispatch_after
 * @property CarbonImmutable|null $sent_at
 * @property int $attempts
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class WhatsappCampaignRecipient extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'whatsapp_campaign_recipients';

    protected $fillable = [
        'campaign_id',
        'organization_id',
        'name',
        'phone_input',
        'phone',
        'status',
        'failure_reason',
        'external_message_id',
        'dispatch_after',
        'sent_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsappCampaignRecipientStatus::class,
            'dispatch_after' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<WhatsappCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaign::class, 'campaign_id');
    }

    /**
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WhatsappCampaignRecipientStatus::Pending);
    }
}

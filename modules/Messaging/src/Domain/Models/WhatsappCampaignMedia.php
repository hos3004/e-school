<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Concerns\HasModuleFactory;
use Shared\Concerns\HasUlid;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string $organization_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $position
 * @property CarbonImmutable|null $deleted_file_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class WhatsappCampaignMedia extends Model
{
    use HasModuleFactory;
    use HasUlid;

    protected $table = 'whatsapp_campaign_media';

    protected $fillable = [
        'campaign_id',
        'organization_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'position',
        'deleted_file_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'position' => 'integer',
            'deleted_file_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<WhatsappCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaign::class, 'campaign_id');
    }
}

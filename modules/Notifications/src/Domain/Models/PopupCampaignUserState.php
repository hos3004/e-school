<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Shared\Concerns\HasUlid;

/**
 * حالة مستخدم واحد تجاه حملة واحدة — تُنشأ عند أول ظهور فعلي فقط.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $user_id
 * @property string $organization_id
 * @property CarbonImmutable|null $first_seen_at
 * @property CarbonImmutable|null $last_seen_at
 * @property int $impressions_count
 * @property CarbonImmutable|null $dismissed_at
 * @property CarbonImmutable|null $acknowledged_at
 * @property CarbonImmutable|null $clicked_at
 * @property string|null $login_marker
 */
final class PopupCampaignUserState extends Model
{
    use HasUlid;

    protected $table = 'popup_campaign_user_state';

    public const UPDATED_AT = null;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'organization_id',
        'first_seen_at',
        'last_seen_at',
        'impressions_count',
        'dismissed_at',
        'acknowledged_at',
        'clicked_at',
        'login_marker',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'impressions_count' => 'integer',
            'dismissed_at' => 'immutable_datetime',
            'acknowledged_at' => 'immutable_datetime',
            'clicked_at' => 'immutable_datetime',
        ];
    }
}

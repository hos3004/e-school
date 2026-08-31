<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupFrequency;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Shared\Concerns\HasUlid;

/**
 * حملة رسالة منبثقة — مخزّنة مرة واحدة، والأهلية تُحسب عند الطلب.
 * لا Fan-out ولا صفوف لكل مستخدم وقت النشر.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $internal_name
 * @property PopupType $type
 * @property PopupCampaignStatus $status
 * @property int $priority
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property list<string> $audiences
 * @property PopupPlacement $placement
 * @property string|null $page_key
 * @property PopupFrequency $frequency
 * @property bool $is_dismissible
 * @property bool $requires_acknowledgement
 * @property array<string, string>|null $acknowledgement_label
 * @property array<string, string>|null $action_label
 * @property string|null $action_type
 * @property string|null $action_target
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $published_at
 * @property string|null $published_by
 * @property string|null $created_by
 * @property string|null $updated_by
 */
final class PopupCampaign extends Model
{
    use HasUlid;
    use SoftDeletes;

    protected $table = 'popup_campaigns';

    protected $fillable = [
        'organization_id',
        'internal_name',
        'type',
        'status',
        'priority',
        'title',
        'body',
        'audiences',
        'placement',
        'page_key',
        'frequency',
        'is_dismissible',
        'requires_acknowledgement',
        'acknowledgement_label',
        'action_label',
        'action_type',
        'action_target',
        'starts_at',
        'ends_at',
        'published_at',
        'published_by',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PopupType::class,
            'status' => PopupCampaignStatus::class,
            'priority' => 'integer',
            'title' => 'array',
            'body' => 'array',
            'audiences' => 'array',
            'placement' => PopupPlacement::class,
            'frequency' => PopupFrequency::class,
            'is_dismissible' => 'boolean',
            'requires_acknowledgement' => 'boolean',
            'acknowledgement_label' => 'array',
            'action_label' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * قاعدة حماية المستخدم: لا يُسمح بحملة تحبس صاحبها.
     */
    public function hasSafeExit(): bool
    {
        return $this->is_dismissible || $this->requires_acknowledgement;
    }

    /** داخل نافذة العرض الآن (UTC). */
    public function isWithinWindow(CarbonImmutable $now): bool
    {
        return $this->starts_at->lessThanOrEqualTo($now)
            && ($this->ends_at === null || $this->ends_at->greaterThan($now));
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

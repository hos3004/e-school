<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Notifications\Domain\Enums\PopupAudience;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupDisplayMode;
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
 * @property list<string>|null $excluded_audiences
 * @property PopupPlacement $placement
 * @property PopupDisplayMode $display_mode
 * @property string|null $page_key
 * @property PopupFrequency $frequency
 * @property bool $is_dismissible
 * @property bool $requires_acknowledgement
 * @property int|null $auto_dismiss_seconds
 * @property array<string, string>|null $acknowledgement_label
 * @property array<string, string>|null $action_label
 * @property string|null $action_type
 * @property string|null $action_target
 * @property list<array{text: string, url: string}>|null $links
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
        'excluded_audiences',
        'placement',
        'display_mode',
        'page_key',
        'frequency',
        'is_dismissible',
        'requires_acknowledgement',
        'auto_dismiss_seconds',
        'acknowledgement_label',
        'action_label',
        'action_type',
        'action_target',
        'links',
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
            'excluded_audiences' => 'array',
            'placement' => PopupPlacement::class,
            'display_mode' => PopupDisplayMode::class,
            'frequency' => PopupFrequency::class,
            'is_dismissible' => 'boolean',
            'requires_acknowledgement' => 'boolean',
            'auto_dismiss_seconds' => 'integer',
            'acknowledgement_label' => 'array',
            'action_label' => 'array',
            'links' => 'array',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * قاعدة حماية المستخدم: لا يُسمح بحملة تحبس صاحبها.
     *
     * الإغلاق التلقائي (auto_dismiss_seconds) يُحسب كمخرج آمن أيضًا: حملة
     * غير قابلة للإغلاق يدويًا وغير مطالبة بإقرار، لكنها تختفي من نفسها
     * بعد مدة محددة، لا تحبس المستخدم فعليًا.
     */
    public function hasSafeExit(): bool
    {
        return $this->is_dismissible
            || $this->requires_acknowledgement
            || ($this->auto_dismiss_seconds !== null && $this->auto_dismiss_seconds > 0);
    }

    /**
     * أهلية مستخدم لهذه الحملة حسب الجمهور — المصدر الوحيد لهذا المنطق.
     * الاستثناء يفوز دائمًا: تقاطع مع excluded_audiences يُسقط الأهلية
     * فورًا بصرف النظر عن مطابقة audiences الموجبة. «الجميع»
     * (AllAuthenticated) قيمة مطابقة إيجابية فقط — لا معنى لها في الاستثناء.
     *
     * أي نقطة تفحص أهلية مستخدم لحملة (القائمة النشطة، عرض ميديا، تسجيل
     * تفاعل) يجب أن تستدعي هذه الدالة بدل إعادة كتابة منطق المطابقة، حتى
     * لا تفترق النسخ بمرور الوقت.
     *
     * @param list<string> $userAudiences
     */
    public function isEligibleForAudiences(array $userAudiences): bool
    {
        if (self::audienceIntersects($this->excluded_audiences ?? [], $userAudiences, matchAllAuthenticated: false)) {
            return false;
        }

        return self::audienceIntersects($this->audiences ?? [], $userAudiences, matchAllAuthenticated: true);
    }

    /**
     * @param list<string> $campaignAudiences
     * @param list<string> $userAudiences
     */
    private static function audienceIntersects(array $campaignAudiences, array $userAudiences, bool $matchAllAuthenticated): bool
    {
        if ($matchAllAuthenticated && in_array(PopupAudience::AllAuthenticated->value, $campaignAudiences, true)) {
            return true;
        }

        return collect($campaignAudiences)->intersect($userAudiences)->isNotEmpty();
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

    /** @return HasMany<PopupCampaignMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(PopupCampaignMedia::class, 'campaign_id')->orderBy('position');
    }
}

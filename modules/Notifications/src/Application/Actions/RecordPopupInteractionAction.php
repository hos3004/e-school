<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignUserState;
use Shared\Support\BusinessRuleViolation;

/**
 * تسجيل تفاعل المستخدم مع النافذة المنبثقة: مشاهدة/إغلاق/إقرار/نقر.
 *
 * قواعد الحماية:
 *  - الحملة يجب أن تكون منشورة داخل مؤسسة المستخدم نفسها (لا IDOR).
 *  - الإغلاق لا يُقبل إلا على حملة قابلة للإغلاق، والإقرار يتطلب خاصية الإقرار.
 *  - كل عملية idempotent: الطوابع الزمنية تُكتب مرة واحدة، والمشاهدات تُجمع فقط.
 */
final class RecordPopupInteractionAction
{
    public const TYPE_IMPRESSION = 'impression';

    public const TYPE_DISMISS = 'dismiss';

    public const TYPE_ACKNOWLEDGE = 'acknowledge';

    public const TYPE_CLICK = 'click';

    private const TYPES = [
        self::TYPE_IMPRESSION,
        self::TYPE_DISMISS,
        self::TYPE_ACKNOWLEDGE,
        self::TYPE_CLICK,
    ];

    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return PopupCampaignUserState الحالة بعد التفاعل
     */
    public function execute(
        string $campaignId,
        string $userId,
        string $organizationId,
        string $type,
        ?string $loginMarker,
    ): PopupCampaignUserState {
        if (!in_array($type, self::TYPES, true)) {
            throw BusinessRuleViolation::make(
                'notifications.popup_invalid_interaction',
                'notifications::popups.errors.invalid_interaction',
            );
        }

        $campaign = PopupCampaign::query()
            ->forOrganization($organizationId)
            ->whereKey($campaignId)
            ->where('status', 'published')
            ->first();

        if ($campaign === null) {
            throw BusinessRuleViolation::make(
                'notifications.popup_not_available',
                'notifications::popups.errors.not_available',
            );
        }

        // قواعد القدرة تُفحص قبل لمس حالة المستخدم.
        if ($type === self::TYPE_DISMISS && !$campaign->is_dismissible) {
            throw BusinessRuleViolation::make(
                'notifications.popup_not_dismissible',
                'notifications::popups.errors.not_dismissible',
            );
        }

        if ($type === self::TYPE_ACKNOWLEDGE && !$campaign->requires_acknowledgement) {
            throw BusinessRuleViolation::make(
                'notifications.popup_no_acknowledgement',
                'notifications::popups.errors.no_acknowledgement',
            );
        }

        if ($type === self::TYPE_CLICK && $campaign->action_type === null) {
            throw BusinessRuleViolation::make(
                'notifications.popup_no_action',
                'notifications::popups.errors.no_action',
            );
        }

        /** @var PopupCampaignUserState $state */
        $state = DB::transaction(function () use ($campaign, $userId, $organizationId, $type, $loginMarker): PopupCampaignUserState {
            /** @var PopupCampaignUserState|null $state */
            $state = PopupCampaignUserState::query()
                ->where('campaign_id', (string) $campaign->getKey())
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($state === null) {
                // الصف يُنشأ عند أول تفاعل فعلي فقط — لا fan-out وقت النشر.
                $state = new PopupCampaignUserState;
                $state->campaign_id = (string) $campaign->getKey();
                $state->user_id = $userId;
                $state->organization_id = $organizationId;
                $state->impressions_count = 0;
                $state->save();
            }

            $now = CarbonImmutable::now('UTC');

            switch ($type) {
                case self::TYPE_IMPRESSION:
                    $state->first_seen_at ??= $now;
                    $state->last_seen_at = $now;
                    $state->impressions_count += 1;

                    if ($loginMarker !== null && $state->login_marker !== $loginMarker) {
                        $state->login_marker = $loginMarker;
                    }
                    break;

                case self::TYPE_DISMISS:
                    if (!$campaign->is_dismissible) {
                        throw BusinessRuleViolation::make(
                            'notifications.popup_not_dismissible',
                            'notifications::popups.errors.not_dismissible',
                        );
                    }

                    $state->dismissed_at ??= $now;
                    break;

                case self::TYPE_ACKNOWLEDGE:
                    if (!$campaign->requires_acknowledgement) {
                        throw BusinessRuleViolation::make(
                            'notifications.popup_no_acknowledgement',
                            'notifications::popups.errors.no_acknowledgement',
                        );
                    }

                    // الإقرار الفعلي يُسجل مرة واحدة — النقرات المتكررة idempotent.
                    $state->acknowledged_at ??= $now;
                    break;

                case self::TYPE_CLICK:
                    if ($campaign->action_type === null) {
                        throw BusinessRuleViolation::make(
                            'notifications.popup_no_action',
                            'notifications::popups.errors.no_action',
                        );
                    }

                    $state->clicked_at ??= $now;
                    break;
            }

            $state->save();

            return $state;
        });

        if ($type === self::TYPE_ACKNOWLEDGE) {
            $this->audit->record(
                organizationId: $organizationId,
                actorId: $userId,
                actorType: 'user',
                action: 'notifications.popup_acknowledged',
                auditableType: 'popup_campaign',
                auditableId: (string) $campaign->getKey(),
                oldValues: null,
                newValues: ['acknowledged' => true],
                reason: null,
            );
        }

        return $state;
    }
}

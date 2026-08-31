<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Shared\Support\BusinessRuleViolation;

/**
 * انتقالات حالة الحملة — عبر آلة الحالات الرسمية فقط، مع سبب مكتوب وتدقيق.
 * لا تعيين حالة بنص ولا بـEloquent مباشر من الواجهة.
 */
final class TransitionPopupCampaignAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(
        PopupCampaign $campaign,
        PopupCampaignStatus $target,
        string $actorId,
        string $reason,
    ): PopupCampaign {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'notifications.popup_reason_required',
                'notifications::popups.errors.reason_required',
            );
        }

        $current = $campaign->status;

        if (!$current->canTransitionTo($target)) {
            throw BusinessRuleViolation::make(
                'notifications.popup_invalid_transition',
                'notifications::popups.errors.invalid_transition',
                ['from' => $current->value, 'to' => $target->value],
            );
        }

        if ($target === PopupCampaignStatus::Published) {
            self::assertPublishable($campaign);
        }

        $old = ['status' => $current->value];

        DB::transaction(function () use ($campaign, $target, $actorId): void {
            $campaign->status = $target;

            if ($target === PopupCampaignStatus::Published && $campaign->published_at === null) {
                $campaign->published_at = CarbonImmutable::now('UTC');
                $campaign->published_by = $actorId;
            }

            $campaign->updated_by = $actorId;
            $campaign->save();
        });

        // مسح أي كاش مستقبلي للمرشحين عند تغيير الحالة (نقاط المسح الموحدة).
        $this->flushCandidateCache($campaign);

        $this->audit->record(
            organizationId: (string) $campaign->organization_id,
            actorId: $actorId,
            actorType: 'user',
            action: 'notifications.popup_campaign_'.$target->value,
            auditableType: 'popup_campaign',
            auditableId: (string) $campaign->getKey(),
            oldValues: $old,
            newValues: ['status' => $target->value],
            reason: trim($reason),
        );

        return $campaign;
    }

    /**
     * شروط النشر: محتوى عربي إلزامي، جمهور واحد فأكثر، خروج آمن،
     * وصفحة قانونية عند SpecificPage.
     */
    public static function assertPublishable(PopupCampaign $campaign): void
    {
        if (trim((string) ($campaign->title['ar'] ?? '')) === ''
            || trim((string) ($campaign->body['ar'] ?? '')) === '') {
            throw BusinessRuleViolation::make(
                'notifications.popup_arabic_content_required',
                'notifications::popups.errors.arabic_content_required',
            );
        }

        if (collect($campaign->audiences ?? [])->isEmpty()) {
            throw BusinessRuleViolation::make(
                'notifications.popup_audience_required',
                'notifications::popups.errors.audience_required',
            );
        }

        if (!$campaign->hasSafeExit()) {
            throw BusinessRuleViolation::make(
                'notifications.popup_unsafe_exit',
                'notifications::popups.errors.unsafe_exit',
            );
        }

        if ($campaign->placement->value === 'specific_page'
            && !PopupPageRegistry::isValid((string) $campaign->page_key)) {
            throw BusinessRuleViolation::make(
                'notifications.popup_invalid_page_key',
                'notifications::popups.errors.invalid_page_key',
            );
        }

        if ($campaign->ends_at !== null && $campaign->ends_at->lessThanOrEqualTo($campaign->starts_at)) {
            throw BusinessRuleViolation::make(
                'notifications.popup_invalid_window',
                'notifications::popups.errors.invalid_window',
            );
        }
    }

    /**
     * مفاتيح كاش المرشحين — تُمسح عند النشر/الإيقاف/الاستئناف/الأرشفة.
     */
    private function flushCandidateCache(PopupCampaign $campaign): void
    {
        foreach (PopupPlacement::cases() as $placement) {
            Cache::forget(self::cacheKey((string) $campaign->organization_id, $placement->value));
        }
    }

    public static function cacheKey(string $organizationId, string $placement): string
    {
        return sprintf('popup_candidates:%s:%s', $organizationId, $placement);
    }
}

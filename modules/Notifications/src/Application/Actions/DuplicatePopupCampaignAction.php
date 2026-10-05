<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Shared\Support\BusinessRuleViolation;

/**
 * نسخ حملة قائمة كمسودة جديدة — الأرشفة بديل الحذف، والنسخ بديل التعديل
 * الجذري للحملات المنشورة تاريخيًا.
 */
final class DuplicatePopupCampaignAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(PopupCampaign $source, string $actorId, string $reason): PopupCampaign
    {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'notifications.popup_reason_required',
                'notifications::popups.errors.reason_required',
            );
        }

        /** @var PopupCampaign $copy */
        $copy = $source->replicate(['published_at', 'published_by']);

        $copy->status = PopupCampaignStatus::Draft;
        $copy->internal_name = $source->internal_name.' — '.__('notifications::popups.duplicate_suffix');
        $copy->starts_at = CarbonImmutable::now('UTC')->addHour();
        $copy->ends_at = null;
        $copy->published_at = null;
        $copy->published_by = null;
        $copy->created_by = $actorId;
        $copy->updated_by = $actorId;
        $copy->save();

        $this->audit->record(
            organizationId: (string) $source->organization_id,
            actorId: $actorId,
            actorType: 'user',
            action: 'notifications.popup_campaign_duplicated',
            auditableType: 'popup_campaign',
            auditableId: (string) $copy->getKey(),
            oldValues: ['source_id' => (string) $source->getKey()],
            newValues: ['status' => PopupCampaignStatus::Draft->value],
            reason: trim($reason),
        );

        return $copy;
    }
}

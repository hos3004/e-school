<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Shared\Support\BusinessRuleViolation;

/**
 * حفظ الحملة (إنشاء/تعديل) — لا حفظ Eloquent مباشر من الواجهة.
 *
 * قاعدة المحتوى: لا يُعدَّل المحتوى أو الجمهور أثناء Published؛
 * يجب الإيقاف مؤقتًا أولًا. تعديل الجدولة يتطلب السبب ويُدقَّق دائمًا.
 */
final class SavePopupCampaignAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, string>|null $scheduleChanges
     */
    public function execute(
        ?PopupCampaign $campaign,
        array $attributes,
        ?array $scheduleChanges,
        string $actorId,
        string $reason,
    ): PopupCampaign {
        if (trim($reason) === '') {
            throw BusinessRuleViolation::make(
                'notifications.popup_reason_required',
                'notifications::popups.errors.reason_required',
            );
        }

        $isCreate = $campaign === null;
        /** @var PopupCampaign $campaign */
        $campaign = $campaign ?? new PopupCampaign;

        // حماية المحتوى أثناء النشر: إيقاف مؤقت أولًا.
        if (!$isCreate && $campaign->status === PopupCampaignStatus::Published) {
            throw BusinessRuleViolation::make(
                'notifications.popup_locked_while_published',
                'notifications::popups.errors.locked_while_published',
            );
        }

        $old = $isCreate
            ? null
            : collect($campaign->only([
                'internal_name', 'type', 'title', 'body', 'audiences', 'placement',
                'page_key', 'frequency', 'is_dismissible', 'requires_acknowledgement',
                'acknowledgement_label', 'action_label', 'action_type', 'action_target',
                'starts_at', 'ends_at',
            ]))
                ->map(static fn (mixed $value): string => is_string($value) ? $value : (string) json_encode($value))
                ->all();

        DB::transaction(function () use ($campaign, $attributes, $actorId): void {
            $campaign->fill($attributes);
            $campaign->created_by = $campaign->created_by ?? $actorId;
            $campaign->updated_by = $actorId;
            $campaign->save();
        });

        $this->audit->record(
            organizationId: (string) $campaign->organization_id,
            actorId: $actorId,
            actorType: 'user',
            action: $isCreate ? 'notifications.popup_campaign_created' : 'notifications.popup_campaign_updated',
            auditableType: 'popup_campaign',
            auditableId: (string) $campaign->getKey(),
            oldValues: $old,
            newValues: collect($campaign->only(array_keys($old !== null ? $old : $attributes)))
                ->map(static fn (mixed $value): string => is_string($value) ? $value : (string) json_encode($value))
                ->all(),
            reason: trim($reason),
        );

        return $campaign;
    }
}

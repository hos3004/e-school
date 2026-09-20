<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;

/**
 * إنهاء الحملة متى لم يبق فيها منتظِر.
 *
 * يناديها آخر مستلم تخرج رسالته، وتناديها شبكة الأمان بعد أن تُغلق ما انقطع.
 * الشرط على الحالة داخل التحديث يمنع سباق نداءين يُنهيان الحملة معًا، وهو أيضًا
 * ما يمنع إنهاء حملة أوقفها المرسِل بيده.
 */
final class SettleWhatsappCampaignAction
{
    public function execute(string $campaignId): bool
    {
        $stillPending = WhatsappCampaignRecipient::query()
            ->where('campaign_id', $campaignId)
            ->pending()
            ->exists();

        if ($stillPending) {
            return false;
        }

        $now = CarbonImmutable::now('UTC');

        return WhatsappCampaign::query()
            ->whereKey($campaignId)
            ->where('status', WhatsappCampaignStatus::Running)
            ->update([
                'status' => WhatsappCampaignStatus::Completed->value,
                'completed_at' => $now,
                'media_expires_at' => $now->addDays((int) config('messaging.campaigns.media.retention_days', 7)),
                'updated_at' => $now,
            ]) === 1;
    }
}

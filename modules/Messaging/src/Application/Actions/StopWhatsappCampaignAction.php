<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إيقاف حملة جارية: من بقي في الانتظار يُلغى فلا يخرج له شيء.
 *
 * الإيقاف لا يسترجع ما خرج فعلًا — الرسالة المُسلَّمة للمزوّد وصلت. ما يضمنه
 * الزر أن ما لم يخرج بعد لن يخرج.
 */
final readonly class StopWhatsappCampaignAction
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    public function execute(WhatsappCampaign $campaign, string $reason): WhatsappCampaign
    {
        if (!$campaign->status->canTransitionTo(WhatsappCampaignStatus::Stopped)) {
            throw BusinessRuleViolation::make(
                'messaging.campaign_not_stoppable',
                'messaging::errors.campaign_not_stoppable',
            );
        }

        $this->transaction->run(function () use ($campaign, $reason): void {
            WhatsappCampaignRecipient::query()
                ->where('campaign_id', $campaign->getKey())
                ->pending()
                ->update([
                    'status' => WhatsappCampaignRecipientStatus::Cancelled,
                    'failure_reason' => $reason,
                    'updated_at' => CarbonImmutable::now('UTC'),
                ]);

            $campaign->status = WhatsappCampaignStatus::Stopped;
            $campaign->completed_at = CarbonImmutable::now('UTC');
            $campaign->media_expires_at = CarbonImmutable::now('UTC')
                ->addDays((int) config('messaging.campaigns.media.retention_days', 7));
            $campaign->save();
        });

        return $campaign->refresh();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
        private Transaction $transaction,
    ) {}

    public function execute(WhatsappCampaign $campaign, string $reason, ?string $actorId = null): WhatsappCampaign
    {
        if (!$campaign->status->canTransitionTo(WhatsappCampaignStatus::Stopped)) {
            throw BusinessRuleViolation::make(
                'messaging.campaign_not_stoppable',
                'messaging::errors.campaign_not_stoppable',
            );
        }

        $previousStatus = $campaign->status;
        $cancelled = 0;

        $this->transaction->run(function () use ($campaign, $reason, &$cancelled): void {
            /*
             * المحجوز في sending لا يُلغى: مهمته تنادي المزوّد الآن، وقد قبل
             * رسالته بالفعل. تكتب هي نتيجتها، والزر يضمن ما لم يخرج بعد.
             */
            $cancelled = WhatsappCampaignRecipient::query()
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

        $this->audit->record(
            organizationId: $campaign->organization_id,
            actorId: $actorId,
            actorType: 'user',
            action: 'messaging.whatsapp_campaign_stopped',
            auditableType: 'whatsapp_campaign',
            auditableId: (string) $campaign->getKey(),
            oldValues: ['status' => $previousStatus->value],
            newValues: [
                'status' => WhatsappCampaignStatus::Stopped->value,
                'cancelled_count' => $cancelled,
            ],
            reason: $reason,
        );

        return $campaign->refresh();
    }
}

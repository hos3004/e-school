<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Messaging\Application\Jobs\SendWhatsappCampaignMessage;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * بدء الحملة: يُحسب لكل مستلم موعده، ثم تُوزَّع مهمة مؤجلة إلى موعده.
 *
 * التباعد يُحسب مرة واحدة هنا ويُخزَّن في dispatch_after، ولا يُترك لعاملٍ ينام
 * بين رسالة وأخرى: عامل نائم يحجز عامل الطابور دقائق طويلة ويضيع تباعده كله
 * عند أول إعادة تشغيل. الموعد المخزَّن يبقى بعد إعادة التشغيل، ومنه تلتقط
 * شبكة الأمان ما فات موعده.
 */
final readonly class StartWhatsappCampaignAction
{
    public function __construct(
        private GreenApiConnections $connections,
        private AuditRecorder $audit,
        private Transaction $transaction,
    ) {}

    public function execute(WhatsappCampaign $campaign, ?string $actorId = null): WhatsappCampaign
    {
        if (!$campaign->status->canTransitionTo(WhatsappCampaignStatus::Running)) {
            throw BusinessRuleViolation::make(
                'messaging.campaign_not_startable',
                'messaging::errors.campaign_not_startable',
            );
        }

        if (!$this->connections->isChannelEnabled($campaign->organization_id)) {
            throw BusinessRuleViolation::make(
                'messaging.campaign_channel_disabled',
                'messaging::errors.campaign_channel_disabled',
            );
        }

        $pending = WhatsappCampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->pending()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id']);

        if ($pending->isEmpty()) {
            throw BusinessRuleViolation::make(
                'messaging.campaign_recipients_empty',
                'messaging::errors.campaign_recipients_empty',
            );
        }

        $start = CarbonImmutable::now('UTC');
        $offsetSeconds = 0;
        $schedule = [];

        foreach ($pending as $index => $recipient) {
            // الأول يخرج فورًا؛ التباعد بين رسالة والتي تليها لا قبل الأولى.
            if ($index > 0) {
                $offsetSeconds += random_int($campaign->delay_min_seconds, $campaign->delay_max_seconds);
            }

            $schedule[(string) $recipient->getKey()] = $offsetSeconds;
        }

        $this->transaction->run(function () use ($campaign, $schedule, $start): void {
            foreach ($schedule as $recipientId => $offsetSeconds) {
                WhatsappCampaignRecipient::query()
                    ->whereKey($recipientId)
                    ->update(['dispatch_after' => $start->addSeconds($offsetSeconds)]);
            }

            $campaign->status = WhatsappCampaignStatus::Running;
            $campaign->started_at = $start;
            $campaign->save();
        });

        $queue = (string) config('messaging.campaigns.queue', 'notifications');

        foreach ($schedule as $recipientId => $offsetSeconds) {
            SendWhatsappCampaignMessage::dispatch((string) $recipientId)
                ->onQueue($queue)
                ->delay($start->addSeconds($offsetSeconds));
        }

        $this->audit->record(
            organizationId: $campaign->organization_id,
            actorId: $actorId,
            actorType: 'user',
            action: 'messaging.whatsapp_campaign_started',
            auditableType: 'whatsapp_campaign',
            auditableId: (string) $campaign->getKey(),
            oldValues: ['status' => WhatsappCampaignStatus::Draft->value],
            newValues: [
                'status' => WhatsappCampaignStatus::Running->value,
                'recipient_count' => count($schedule),
                'span_seconds' => $offsetSeconds,
            ],
            reason: $campaign->reason,
        );

        return $campaign->refresh();
    }
}

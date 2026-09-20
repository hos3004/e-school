<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Messaging\Application\Actions\SettleWhatsappCampaignAction;
use Modules\Messaging\Application\Jobs\SendWhatsappCampaignMessage;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;

/**
 * شبكة أمان الحملات الجارية.
 *
 * مستلم فات موعده ولم تُحاول مهمته قط (attempts = 0) فُقدت مهمته المؤجلة —
 * يُعاد توزيعها. أما من بدأت محاولته ثم انقطعت (attempts ≥ 1) فلا يُعاد إرساله:
 * المزوّد ربما قبل رسالته قبل الانقطاع، وإعادتها تعني رسالة ثانية لنفس الشخص.
 * يُغلق سطره بـinterrupted ليراه المرسِل ويقرر بنفسه، ولئلا تبقى الحملة معلّقة
 * إلى الأبد بانتظار سطر لن يتحرك.
 */
final class SweepWhatsappCampaignDispatches extends Command
{
    protected $signature = 'whatsapp:campaigns-sweep';

    protected $description = 'إعادة توزيع رسائل الحملات التي فات موعدها، وإغلاق ما انقطع منها.';

    public function handle(SettleWhatsappCampaignAction $settle): int
    {
        $threshold = CarbonImmutable::now('UTC')
            ->subMinutes(max(1, (int) config('messaging.campaigns.overdue_sweep_minutes', 5)));

        $limit = max(1, (int) config('messaging.campaigns.sweep_batch_size', 200));
        $queue = (string) config('messaging.campaigns.queue', 'notifications');

        $runningCampaignIds = WhatsappCampaign::query()
            ->where('status', WhatsappCampaignStatus::Running)
            ->pluck('id')
            ->all();

        if ($runningCampaignIds === []) {
            $this->info(__('messaging::messages.campaign_sweep_done', ['redispatched' => 0, 'closed' => 0]));

            return self::SUCCESS;
        }

        $overdue = WhatsappCampaignRecipient::query()
            ->whereIn('campaign_id', $runningCampaignIds)
            ->pending()
            ->whereNotNull('dispatch_after')
            ->where('dispatch_after', '<=', $threshold)
            ->orderBy('dispatch_after')
            ->limit($limit)
            ->get(['id', 'campaign_id', 'attempts']);

        $redispatched = 0;
        $closed = 0;
        $touchedCampaigns = [];

        foreach ($overdue as $recipient) {
            $touchedCampaigns[(string) $recipient->campaign_id] = true;

            if ($recipient->attempts === 0) {
                SendWhatsappCampaignMessage::dispatch((string) $recipient->getKey())->onQueue($queue);
                $redispatched++;

                continue;
            }

            $closed += WhatsappCampaignRecipient::query()
                ->whereKey($recipient->getKey())
                ->where('status', WhatsappCampaignRecipientStatus::Pending)
                ->update([
                    'status' => WhatsappCampaignRecipientStatus::Failed->value,
                    'failure_reason' => 'interrupted',
                    'updated_at' => CarbonImmutable::now('UTC'),
                ]);
        }

        foreach (array_keys($touchedCampaigns) as $campaignId) {
            $failed = WhatsappCampaignRecipient::query()
                ->where('campaign_id', $campaignId)
                ->where('status', WhatsappCampaignRecipientStatus::Failed)
                ->count();

            WhatsappCampaign::query()->whereKey($campaignId)->update(['failed_count' => $failed]);

            $settle->execute($campaignId);
        }

        $this->info(__('messaging::messages.campaign_sweep_done', [
            'redispatched' => $redispatched,
            'closed' => $closed,
        ]));

        return self::SUCCESS;
    }
}

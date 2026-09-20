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
 * منتظِرٌ فات موعده ولم تُحاول مهمته قط فُقدت مهمته المؤجلة، فيُعاد توزيعه.
 * ومحجوزٌ في sending منذ زمن طويل انقطعت محاولته بين نداء المزوّد وكتابة
 * النتيجة، فلا يُعاد إرساله: المزوّد ربما قبل رسالته، وإعادتها رسالة ثانية
 * لنفس الشخص. يُغلق بـinterrupted ليراه المرسِل ويقرر بنفسه.
 *
 * ثم تُنهى كل حملة جارية لم يبق فيها منتظِر ولا محجوز — حتى الحملة التي مات
 * عاملها بعد آخر رسالة وقبل أن يُنهيها، فلولا ذلك بقيت «جارية» أبدًا ولم يحن
 * لمرفقاتها موعد إتلاف.
 */
final class SweepWhatsappCampaignDispatches extends Command
{
    protected $signature = 'whatsapp:campaigns-sweep';

    protected $description = 'إعادة توزيع رسائل الحملات التي فات موعدها، وإغلاق ما انقطع منها.';

    public function handle(SettleWhatsappCampaignAction $settle): int
    {
        $now = CarbonImmutable::now('UTC');
        $threshold = $now->subMinutes(max(1, (int) config('messaging.campaigns.overdue_sweep_minutes', 5)));
        $limit = max(1, (int) config('messaging.campaigns.sweep_batch_size', 200));
        $queue = (string) config('messaging.campaigns.queue', 'notifications');

        /** @var list<string> $runningCampaignIds */
        $runningCampaignIds = WhatsappCampaign::query()
            ->where('status', WhatsappCampaignStatus::Running)
            ->pluck('id')
            ->all();

        if ($runningCampaignIds === []) {
            $this->info(__('messaging::messages.campaign_sweep_done', ['redispatched' => 0, 'closed' => 0]));

            return self::SUCCESS;
        }

        $closed = $this->closeInterrupted($runningCampaignIds, $threshold, $now, $limit);
        $redispatched = $this->redispatchOverdue($runningCampaignIds, $threshold, $now, $limit, $queue);

        foreach ($runningCampaignIds as $campaignId) {
            $this->refreshCounts($campaignId);
            $settle->execute($campaignId);
        }

        $this->info(__('messaging::messages.campaign_sweep_done', [
            'redispatched' => $redispatched,
            'closed' => $closed,
        ]));

        return self::SUCCESS;
    }

    /**
     * @param list<string> $campaignIds
     */
    private function closeInterrupted(
        array $campaignIds,
        CarbonImmutable $threshold,
        CarbonImmutable $now,
        int $limit,
    ): int {
        $stalled = WhatsappCampaignRecipient::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', WhatsappCampaignRecipientStatus::Sending)
            ->where('updated_at', '<=', $threshold)
            ->orderBy('updated_at')
            ->limit($limit)
            ->pluck('id')
            ->all();

        if ($stalled === []) {
            return 0;
        }

        return WhatsappCampaignRecipient::query()
            ->whereIn('id', $stalled)
            ->where('status', WhatsappCampaignRecipientStatus::Sending)
            ->update([
                'status' => WhatsappCampaignRecipientStatus::Failed->value,
                'failure_reason' => 'interrupted',
                'updated_at' => $now,
            ]);
    }

    /**
     * إعادة التوزيع تُعيد التباعد أيضًا.
     *
     * توزيع مئتي رسالة دفعةً واحدة بعد انقطاع هو الحظر نفسه الذي وُجدت المهلة
     * لتفاديه، فتُمنح كل رسالة موعدًا جديدًا بفارق عشوائي كما لو بدأت الحملة الآن.
     *
     * @param list<string> $campaignIds
     */
    private function redispatchOverdue(
        array $campaignIds,
        CarbonImmutable $threshold,
        CarbonImmutable $now,
        int $limit,
        string $queue,
    ): int {
        $overdue = WhatsappCampaignRecipient::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', WhatsappCampaignRecipientStatus::Pending)
            ->where('attempts', 0)
            ->whereNotNull('dispatch_after')
            ->where('dispatch_after', '<=', $threshold)
            ->orderBy('dispatch_after')
            ->limit($limit)
            ->get(['id', 'campaign_id']);

        if ($overdue->isEmpty()) {
            return 0;
        }

        /** @var array<string, array{min: int, max: int}> $pacing */
        $pacing = WhatsappCampaign::query()
            ->whereIn('id', $campaignIds)
            ->get(['id', 'delay_min_seconds', 'delay_max_seconds'])
            ->mapWithKeys(static fn (WhatsappCampaign $campaign): array => [
                (string) $campaign->getKey() => [
                    'min' => $campaign->delay_min_seconds,
                    'max' => $campaign->delay_max_seconds,
                ],
            ])
            ->all();

        $offsets = [];
        $redispatched = 0;

        foreach ($overdue as $recipient) {
            $campaignId = (string) $recipient->campaign_id;
            $gap = $pacing[$campaignId] ?? ['min' => 5, 'max' => 15];

            $offsets[$campaignId] = isset($offsets[$campaignId])
                ? $offsets[$campaignId] + random_int($gap['min'], $gap['max'])
                : 0;

            $moment = $now->addSeconds($offsets[$campaignId]);

            WhatsappCampaignRecipient::query()
                ->whereKey($recipient->getKey())
                ->where('status', WhatsappCampaignRecipientStatus::Pending)
                ->update(['dispatch_after' => $moment, 'updated_at' => $now]);

            SendWhatsappCampaignMessage::dispatch((string) $recipient->getKey())
                ->onQueue($queue)
                ->delay($moment);

            $redispatched++;
        }

        return $redispatched;
    }

    /**
     * العدّادات تُعاد من الحقيقة لا بالزيادة: إغلاق شبكة الأمان لسطر لم يمر
     * بعدّاد المهمة، فبدون هذا تبقى الأرقام المعروضة أقل من الواقع.
     */
    private function refreshCounts(string $campaignId): void
    {
        $counts = WhatsappCampaignRecipient::query()
            ->where('campaign_id', $campaignId)
            ->whereIn('status', [
                WhatsappCampaignRecipientStatus::Sent,
                WhatsappCampaignRecipientStatus::Failed,
            ])
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status')
            ->all();

        WhatsappCampaign::query()->whereKey($campaignId)->update([
            'sent_count' => (int) ($counts['sent'] ?? 0),
            'failed_count' => (int) ($counts['failed'] ?? 0),
        ]);
    }
}

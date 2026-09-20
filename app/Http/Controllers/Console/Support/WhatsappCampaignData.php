<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;

/**
 * بيانات قسم الحملات كما تظهر في صفحة واتساب.
 */
final class WhatsappCampaignData
{
    /**
     * @return array<string, mixed>
     */
    public function forOrganization(string $organizationId): array
    {
        $campaigns = WhatsappCampaign::query()
            ->forOrganization($organizationId)
            ->orderByDesc('created_at')
            ->limit((int) config('notifications.admin_hub.max_items', 100))
            ->get();

        $delay = (array) config('messaging.campaigns.delay', []);
        $media = (array) config('messaging.campaigns.media', []);

        return [
            'items' => $campaigns
                ->map(fn (WhatsappCampaign $campaign): array => $this->summary($campaign))
                ->values()
                ->all(),
            'limits' => [
                'maxRecipients' => (int) config('messaging.campaigns.max_recipients', 2000),
                'delayFloor' => (int) ($delay['floor_seconds'] ?? 3),
                'delayCeiling' => (int) ($delay['ceiling_seconds'] ?? 3600),
                'defaultDelayMin' => (int) ($delay['default_min_seconds'] ?? 5),
                'defaultDelayMax' => (int) ($delay['default_max_seconds'] ?? 15),
                'maxMediaFiles' => (int) ($media['max_files'] ?? 5),
                'maxMediaKilobytes' => (int) ($media['max_size_kilobytes'] ?? 16384),
                'mediaRetentionDays' => (int) ($media['retention_days'] ?? 7),
            ],
            /** @var list<string> */
            'placeholders' => (array) config('messaging.campaigns.placeholders', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(WhatsappCampaign $campaign): array
    {
        $counts = WhatsappCampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status')
            ->all();

        return [
            'id' => (string) $campaign->getKey(),
            'name' => $campaign->name,
            'body' => $campaign->body,
            'status' => $campaign->status->value,
            'total_recipients' => $campaign->total_recipients,
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'sent' => (int) ($counts['sent'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
                'invalid' => (int) ($counts['invalid'] ?? 0),
            ],
            'delay_min_seconds' => $campaign->delay_min_seconds,
            'delay_max_seconds' => $campaign->delay_max_seconds,
            'media_count' => $campaign->media()->whereNull('deleted_file_at')->count(),
            'created_at' => $campaign->created_at?->toIso8601String(),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'completed_at' => $campaign->completed_at?->toIso8601String(),
            'media_expires_at' => $campaign->media_expires_at?->toIso8601String(),
        ];
    }

    /**
     * الأرقام المرفوضة بأسمائها — هي ما يصحّحه المرسِل بيده.
     *
     * @return list<array<string, mixed>>
     */
    public function problems(WhatsappCampaign $campaign): array
    {
        return WhatsappCampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->whereIn('status', ['invalid', 'failed'])
            ->orderBy('created_at')
            ->limit(500)
            ->get(['id', 'name', 'phone_input', 'phone', 'status', 'failure_reason'])
            ->map(static fn (WhatsappCampaignRecipient $row): array => [
                'id' => (string) $row->getKey(),
                'name' => $row->name,
                'phone_input' => $row->phone_input,
                'phone' => $row->phone,
                'status' => $row->status->value,
                'reason' => $row->failure_reason,
            ])
            ->values()
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignMedia;

/**
 * إتلاف مرفقات الحملات المنتهية بعد مدة الاحتفاظ.
 *
 * الملف يُحذف ويبقى سطره في الجدول موسومًا بموعد حذفه: سجلّ الحملة يظل يقول
 * ماذا أُرسل مع الرسالة وإن لم تعد نسخته محفوظة. المدة إعداد لا رقم في الكود.
 */
final class PruneWhatsappCampaignMedia extends Command
{
    protected $signature = 'whatsapp:campaigns-prune-media';

    protected $description = 'حذف مرفقات حملات واتساب التي انقضت مدة الاحتفاظ بها.';

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');

        $campaignIds = WhatsappCampaign::query()
            ->whereNotNull('media_expires_at')
            ->where('media_expires_at', '<=', $now)
            ->whereNull('media_pruned_at')
            ->pluck('id')
            ->all();

        if ($campaignIds === []) {
            $this->info(__('messaging::messages.campaign_media_pruned', ['count' => 0]));

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($campaignIds as $campaignId) {
            $files = WhatsappCampaignMedia::query()
                ->where('campaign_id', $campaignId)
                ->whereNull('deleted_file_at')
                ->get();

            foreach ($files as $file) {
                $disk = Storage::disk($file->disk);

                if ($disk->exists($file->path)) {
                    $disk->delete($file->path);
                }

                $file->deleted_file_at = $now;
                $file->save();
                $deleted++;
            }

            WhatsappCampaign::query()->whereKey($campaignId)->update([
                'media_pruned_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->info(__('messaging::messages.campaign_media_pruned', ['count' => $deleted]));

        return self::SUCCESS;
    }
}

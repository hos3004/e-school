<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Messaging\Application\Services\CampaignRecipientListBuilder;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignMedia;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إنشاء حملة مسودّة: تُحفظ القائمة والمرفقات ولا يخرج شيء بعد.
 *
 * الفصل بين الإنشاء والبدء مقصود: بين الخطوتين يرى المرسِل العدد المقبول
 * والأرقام المرفوضة بأسمائها، فيصحّحها قبل أن تخرج رسالة واحدة.
 */
final readonly class CreateWhatsappCampaignAction
{
    public function __construct(
        private CampaignRecipientListBuilder $listBuilder,
        private Transaction $transaction,
    ) {}

    /**
     * @param list<array{name: string|null, phone_input: string}> $rows
     * @param list<UploadedFile> $media
     */
    public function execute(
        string $organizationId,
        string $actorId,
        string $name,
        string $body,
        string $reason,
        array $rows,
        array $media,
        int $delayMinSeconds,
        int $delayMaxSeconds,
    ): WhatsappCampaign {
        if ($delayMinSeconds > $delayMaxSeconds) {
            throw BusinessRuleViolation::make(
                'messaging.campaign_delay_range_invalid',
                'messaging::errors.campaign_delay_range_invalid',
            );
        }

        $list = $this->listBuilder->build($rows);

        if ($list['accepted'] === []) {
            throw BusinessRuleViolation::make(
                'messaging.campaign_recipients_empty',
                'messaging::errors.campaign_recipients_empty',
            );
        }

        $maximum = (int) config('messaging.campaigns.max_recipients', 2000);

        if (count($list['accepted']) > $maximum) {
            throw BusinessRuleViolation::make(
                'messaging.campaign_recipients_exceeded',
                'messaging::errors.campaign_recipients_exceeded',
                ['max' => $maximum],
            );
        }

        /*
         * الملفات تُكتب على القرص قبل فتح المعاملة: كتابة ملف ليست جزءًا من
         * معاملة قاعدة البيانات، ومحاولة التراجع عنها داخلها وهمٌ. فشل الحفظ
         * بعد الكتابة يترك ملفات يتيمة ينظّفها منظّف المرفقات لاحقًا.
         */
        $stored = $this->storeMedia($organizationId, $media);

        return $this->transaction->run(function () use (
            $organizationId,
            $actorId,
            $name,
            $body,
            $reason,
            $list,
            $stored,
            $delayMinSeconds,
            $delayMaxSeconds,
        ): WhatsappCampaign {
            $campaign = new WhatsappCampaign;
            $campaign->fill([
                'organization_id' => $organizationId,
                'created_by' => $actorId,
                'name' => $name,
                'body' => $body,
                'status' => WhatsappCampaignStatus::Draft,
                'reason' => $reason,
                'delay_min_seconds' => $delayMinSeconds,
                'delay_max_seconds' => $delayMaxSeconds,
                'total_recipients' => count($list['accepted']),
            ]);
            $campaign->save();

            foreach ($list['accepted'] as $row) {
                $recipient = new WhatsappCampaignRecipient;
                $recipient->fill([
                    'campaign_id' => $campaign->getKey(),
                    'organization_id' => $organizationId,
                    'name' => $row['name'],
                    'phone_input' => $row['phone_input'],
                    'phone' => $row['phone'],
                    'status' => WhatsappCampaignRecipientStatus::Pending,
                ]);
                $recipient->save();
            }

            /*
             * الأرقام المرفوضة تُحفظ أيضًا: بقاؤها داخل الحملة هو ما يجعل
             * «13 رقمًا يحتاج مراجعة» قائمةً يفتحها المرسِل ويصحّحها، لا رقمًا
             * في تقرير اختفت تفاصيله بإغلاق الصفحة.
             */
            foreach ($list['rejected'] as $row) {
                $recipient = new WhatsappCampaignRecipient;
                $recipient->fill([
                    'campaign_id' => $campaign->getKey(),
                    'organization_id' => $organizationId,
                    'name' => $row['name'],
                    'phone_input' => $row['phone_input'],
                    'phone' => null,
                    'status' => WhatsappCampaignRecipientStatus::Invalid,
                    'failure_reason' => $row['reason'],
                ]);
                $recipient->save();
            }

            foreach ($stored as $position => $file) {
                $record = new WhatsappCampaignMedia;
                $record->fill([
                    'campaign_id' => $campaign->getKey(),
                    'organization_id' => $organizationId,
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'original_name' => $file['original_name'],
                    'mime_type' => $file['mime_type'],
                    'size_bytes' => $file['size_bytes'],
                    'position' => $position,
                ]);
                $record->save();
            }

            return $campaign;
        });
    }

    /**
     * @param list<UploadedFile> $media
     * @return list<array{disk: string, path: string, original_name: string, mime_type: string, size_bytes: int}>
     */
    private function storeMedia(string $organizationId, array $media): array
    {
        if ($media === []) {
            return [];
        }

        $disk = (string) config('messaging.campaigns.media.disk', 'local');
        $directory = trim((string) config('messaging.campaigns.media.directory', 'whatsapp-campaigns'), '/');
        $stored = [];

        foreach ($media as $file) {
            $path = $file->store($directory.'/'.$organizationId, $disk);

            if (!is_string($path) || $path === '') {
                throw BusinessRuleViolation::make(
                    'messaging.campaign_media_store_failed',
                    'messaging::errors.campaign_media_store_failed',
                );
            }

            $stored[] = [
                'disk' => $disk,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => mb_substr((string) (Storage::disk($disk)->mimeType($path) ?: $file->getClientMimeType()), 0, 128),
                'size_bytes' => (int) Storage::disk($disk)->size($path),
            ];
        }

        return $stored;
    }
}

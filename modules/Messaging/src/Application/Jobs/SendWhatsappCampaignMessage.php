<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Integrations\Domain\Contracts\GreenApiConnections;
use Modules\Integrations\Domain\Contracts\WhatsAppDirectSender;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Modules\Messaging\Application\Actions\SettleWhatsappCampaignAction;
use Modules\Messaging\Application\Services\CampaignMessageComposer;
use Modules\Messaging\Domain\Enums\WhatsappCampaignRecipientStatus;
use Modules\Messaging\Domain\Enums\WhatsappCampaignStatus;
use Modules\Messaging\Domain\Models\WhatsappCampaign;
use Modules\Messaging\Domain\Models\WhatsappCampaignMedia;
use Modules\Messaging\Domain\Models\WhatsappCampaignRecipient;
use Throwable;

/**
 * إرسال رسالة حملة إلى مستلم واحد.
 *
 * المهمة تحجز السطر بنقله إلى sending قبل أي نداء للمزوّد. الحجز تحديثٌ مشروط
 * على الحالة، فمن يخسر السباق ينصرف بلا إرسال: توزيع المستلم نفسه مرتين — من
 * شبكة الأمان مع مهمته الأصلية مثلًا — لا يعني رسالتين لنفس الشخص.
 */
final class SendWhatsappCampaignMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * إعادة المحاولة عند فشل الشبكة تتم داخل البوابة نفسها؛ إعادة تشغيل المهمة
     * كاملةً كانت تعني رسالة ثانية لمن وصلته الأولى.
     */
    public int $tries = 1;

    public function __construct(
        public readonly string $recipientId,
    ) {}

    public function handle(
        WhatsAppDirectSender $sender,
        GreenApiConnections $connections,
        CampaignMessageComposer $composer,
        SettleWhatsappCampaignAction $settle,
    ): void {
        $recipient = WhatsappCampaignRecipient::query()->find($this->recipientId);

        if ($recipient === null || $recipient->status !== WhatsappCampaignRecipientStatus::Pending) {
            return;
        }

        $campaign = WhatsappCampaign::query()->find($recipient->campaign_id);

        if ($campaign === null || $campaign->status !== WhatsappCampaignStatus::Running) {
            return;
        }

        $campaignId = (string) $campaign->getKey();

        /*
         * مفتاح إيقاف القناة يجب أن يصدق على الحملات كما يصدق على صندوق
         * الصادر: من ضغط «أوقف واتساب» لا يقبل أن تكمل حملة الخروج بعده.
         */
        if (!$connections->isChannelEnabled($campaign->organization_id)) {
            $this->close($recipient, WhatsappCampaignRecipientStatus::Cancelled, 'channel_disabled');
            $settle->execute($campaignId);

            return;
        }

        $phone = $recipient->phone;

        if ($phone === null) {
            $this->close($recipient, WhatsappCampaignRecipientStatus::Failed, 'phone_missing');
            $settle->execute($campaignId);

            return;
        }

        if (!$this->claim($recipient)) {
            return;
        }

        $result = $this->deliver($sender, $composer, $campaign, $recipient, $phone);

        /*
         * الكتابة النهائية مشروطة ببقاء السطر محجوزًا لهذه المهمة: إن كانت شبكة
         * الأمان قد أغلقته interrupted أثناء نداء المزوّد، فلا يُبعث حيًّا ولا
         * يُزاد عدّاد على حسابه — وإلا تجاوز مجموعُ العدّادات عددَ المستلمين.
         */
        $closed = WhatsappCampaignRecipient::query()
            ->whereKey($recipient->getKey())
            ->where('status', WhatsappCampaignRecipientStatus::Sending)
            ->update($result['accepted']
                ? [
                    'status' => WhatsappCampaignRecipientStatus::Sent->value,
                    'external_message_id' => $result['external_message_id'],
                    'failure_reason' => null,
                    'sent_at' => CarbonImmutable::now('UTC'),
                    'updated_at' => CarbonImmutable::now('UTC'),
                ]
                : [
                    'status' => WhatsappCampaignRecipientStatus::Failed->value,
                    'failure_reason' => mb_substr((string) $result['error'], 0, 255),
                    'updated_at' => CarbonImmutable::now('UTC'),
                ]) === 1;

        if ($closed) {
            WhatsappCampaign::query()
                ->whereKey($campaignId)
                ->increment($result['accepted'] ? 'sent_count' : 'failed_count');
        }

        $settle->execute($campaignId);
    }

    /**
     * @return array{accepted: bool, error: string|null, external_message_id: string|null}
     */
    private function deliver(
        WhatsAppDirectSender $sender,
        CampaignMessageComposer $composer,
        WhatsappCampaign $campaign,
        WhatsappCampaignRecipient $recipient,
        string $phone,
    ): array {
        $text = $composer->compose($campaign->body, $recipient->name);

        $media = WhatsappCampaignMedia::query()
            ->where('campaign_id', $campaign->getKey())
            ->whereNull('deleted_file_at')
            ->orderBy('position')
            ->get();

        try {
            /*
             * المرفقات أولًا ثم النص رسالةً مستقلة: تعليق المزوّد على الملف
             * أقصر من نص الحملة، وإلحاق النص بأول مرفق كان يقصّه بلا إنذار.
             */
            foreach ($media as $file) {
                $disk = Storage::disk($file->disk);

                if (!$disk->exists($file->path)) {
                    return ['accepted' => false, 'error' => 'whatsapp_media_missing', 'external_message_id' => null];
                }

                $stream = $disk->readStream($file->path);

                if (!is_resource($stream)) {
                    return ['accepted' => false, 'error' => 'whatsapp_media_unreadable', 'external_message_id' => null];
                }

                try {
                    $result = $sender->sendFile(
                        $campaign->organization_id,
                        $phone,
                        $stream,
                        $file->original_name,
                    );
                } finally {
                    fclose($stream);
                }

                if (!$result->isAccepted()) {
                    return $this->failure($result);
                }
            }

            if (trim($text) === '' && $media->isNotEmpty()) {
                return ['accepted' => true, 'error' => null, 'external_message_id' => null];
            }

            $result = $sender->sendText($campaign->organization_id, $phone, $text);
        } catch (Throwable $error) {
            return ['accepted' => false, 'error' => $error->getMessage(), 'external_message_id' => null];
        }

        if (!$result->isAccepted()) {
            return $this->failure($result);
        }

        $externalId = $result->providerResponse()['external_message_id'] ?? null;

        return [
            'accepted' => true,
            'error' => null,
            'external_message_id' => is_string($externalId) ? $externalId : null,
        ];
    }

    /**
     * @return array{accepted: false, error: string|null, external_message_id: null}
     */
    private function failure(GatewayResult $result): array
    {
        return ['accepted' => false, 'error' => $result->error(), 'external_message_id' => null];
    }

    /**
     * حجز السطر: pending → sending. التحديث المشروط هو ما يمنع إرسالين.
     */
    private function claim(WhatsappCampaignRecipient $recipient): bool
    {
        return WhatsappCampaignRecipient::query()
            ->whereKey($recipient->getKey())
            ->where('status', WhatsappCampaignRecipientStatus::Pending)
            ->update([
                'status' => WhatsappCampaignRecipientStatus::Sending->value,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]) === 1;
    }

    /**
     * إغلاق سطر لم يصل إلى المزوّد أصلًا — بشرط أنه ما زال منتظرًا.
     */
    private function close(
        WhatsappCampaignRecipient $recipient,
        WhatsappCampaignRecipientStatus $status,
        string $failureReason,
    ): void {
        WhatsappCampaignRecipient::query()
            ->whereKey($recipient->getKey())
            ->where('status', WhatsappCampaignRecipientStatus::Pending)
            ->update([
                'status' => $status->value,
                'failure_reason' => $failureReason,
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);
    }
}

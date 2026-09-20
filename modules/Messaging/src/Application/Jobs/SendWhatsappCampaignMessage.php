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
 * المهمة تحجز سطر المستلم بشرطٍ ذرّي قبل أي نداء للمزوّد: تكرار توزيع نفس
 * المستلم — من شبكة الأمان مع مهمته المؤجلة الأصلية مثلًا — يجب ألا يعني
 * رسالتين لنفس الشخص. من يخسر السباق على السطر ينصرف بلا إرسال.
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

        /*
         * مفتاح إيقاف القناة يجب أن يصدق على الحملات كما يصدق على صندوق
         * الصادر: من ضغط «أوقف واتساب» لا يقبل أن تكمل حملة الخروج بعده.
         */
        if (!$connections->isChannelEnabled($campaign->organization_id)) {
            $this->claim($recipient, WhatsappCampaignRecipientStatus::Cancelled, 'channel_disabled');
            $settle->execute((string) $campaign->getKey());

            return;
        }

        $phone = $recipient->phone;

        if ($phone === null) {
            $this->claim($recipient, WhatsappCampaignRecipientStatus::Failed, 'phone_missing');
            $settle->execute((string) $campaign->getKey());

            return;
        }

        if (!$this->claim($recipient, WhatsappCampaignRecipientStatus::Pending, null)) {
            return;
        }

        $result = $this->deliver($sender, $composer, $campaign, $recipient, $phone);

        if ($result['accepted']) {
            WhatsappCampaignRecipient::query()->whereKey($recipient->getKey())->update([
                'status' => WhatsappCampaignRecipientStatus::Sent,
                'external_message_id' => $result['external_message_id'],
                'failure_reason' => null,
                'sent_at' => CarbonImmutable::now('UTC'),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);

            WhatsappCampaign::query()->whereKey($campaign->getKey())->increment('sent_count');
        } else {
            WhatsappCampaignRecipient::query()->whereKey($recipient->getKey())->update([
                'status' => WhatsappCampaignRecipientStatus::Failed,
                'failure_reason' => mb_substr((string) $result['error'], 0, 255),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);

            WhatsappCampaign::query()->whereKey($campaign->getKey())->increment('failed_count');
        }

        $settle->execute((string) $campaign->getKey());
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
                $path = Storage::disk($file->disk)->path($file->path);

                $result = $sender->sendFile($campaign->organization_id, $phone, $path, $file->original_name);

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
     * حجز السطر: التحديث المشروط على الحالة الحالية هو ما يمنع إرسالين.
     *
     * حجز بحالة Pending يعني «أنا من يعالجه الآن» ويُثبت ذلك بزيادة المحاولات.
     */
    private function claim(
        WhatsappCampaignRecipient $recipient,
        WhatsappCampaignRecipientStatus $status,
        ?string $failureReason,
    ): bool {
        $values = [
            'attempts' => DB::raw('attempts + 1'),
            'updated_at' => CarbonImmutable::now('UTC'),
        ];

        if ($status !== WhatsappCampaignRecipientStatus::Pending) {
            $values['status'] = $status->value;
            $values['failure_reason'] = $failureReason;
        }

        return WhatsappCampaignRecipient::query()
            ->whereKey($recipient->getKey())
            ->where('status', WhatsappCampaignRecipientStatus::Pending)
            ->where('attempts', $recipient->attempts)
            ->update($values) === 1;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Integrations\Domain\Contracts\ChannelGateway;
use Modules\Integrations\Domain\ValueObjects\GatewayMessage;
use Modules\Integrations\Domain\ValueObjects\GatewayResult;
use Modules\Notifications\Application\Actions\MarkNotificationSendingAction;
use Modules\Notifications\Application\Actions\RecordDeliveryAttemptAction;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Throwable;

/**
 * مهمة الإرسال الخلفية — تنفّذ سطرًا واحدًا من الصندوق الصادر.
 *
 * تتخطى ما ليس queued؛ تحجز السطر بحالة sending، تستدعي بوابة
 * القناة، تسجّل محاولة في notification_delivery_attempts، ثم:
 *  - نجاح  → sent.
 *  - فشل قابل للإعادة → إعادة جدولة بتأخير backoff من config.
 *  - فشل نهائي (استُنفد max_retries) → failed.
 */
final class SendQueuedNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * عدد محاولات الـ queue نفسه قبل التخلي — منفصل عن منطق backoff الداخلي.
     */
    public int $tries = 1;

    public function __construct(
        public readonly string $outboxId,
    ) {}

    public function handle(
        MarkNotificationSendingAction $markSending,
        RecordDeliveryAttemptAction $recordAttempt,
        ChannelGateway $gateway,
    ): void {
        $outbox = NotificationOutbox::query()->find($this->outboxId);

        if ($outbox === null || !$outbox->status->isDeliverable()) {
            return;
        }

        try {
            $outbox = $markSending->execute($outbox)->refresh();
        } catch (BusinessRuleViolation) {
            // سبق worker آخر وحجزها أو تغيّرت حالتها — لا شيء نفعله هنا.
            return;
        }

        try {
            $result = $gateway->send($this->gatewayMessage($outbox));
        } catch (Throwable $error) {
            $result = GatewayResult::rejected($error->getMessage(), false);
        }

        if ($result->isAccepted()) {
            $recordAttempt->execute(
                $outbox,
                true,
                providerResponse: $result->providerResponse(),
            );

            return;
        }

        $this->handleFailure($outbox, $result, $recordAttempt);
    }

    private function handleFailure(
        NotificationOutbox $outbox,
        GatewayResult $result,
        RecordDeliveryAttemptAction $recordAttempt,
    ): void {
        $attempt = $recordAttempt->execute(
            $outbox,
            false,
            error: $result->error(),
            providerResponse: $result->providerResponse(),
            retryable: $result->isRetryable(),
        );
        $outbox->refresh();

        if (!$result->isRetryable() || $outbox->status === OutboxStatus::Failed) {
            return;
        }

        $delaySeconds = max(0, (int) now('UTC')->diffInSeconds($outbox->scheduled_for, false));

        self::dispatch($outbox->id)
            ->delay($delaySeconds)
            ->onQueue((string) config('notifications.delivery.queue'));
    }

    private function gatewayMessage(NotificationOutbox $outbox): GatewayMessage
    {
        return new GatewayMessage(
            messageId: $outbox->id,
            organizationId: $outbox->organization_id,
            recipientId: $outbox->user_id,
            category: $outbox->category,
            channel: $outbox->channel instanceof \BackedEnum
                ? (string) $outbox->channel->value
                : (string) $outbox->channel,
            locale: $outbox->locale,
            eventName: $outbox->event_name,
            eventId: $outbox->event_id,
            correlationId: $outbox->correlation_id,
            subject: $outbox->subject,
            body: $outbox->body,
            payload: $outbox->payload,
        );
    }
}

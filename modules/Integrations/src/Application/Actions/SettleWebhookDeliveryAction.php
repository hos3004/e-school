<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Integrations\Application\Concerns\TransitionsDeliveryStatus;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Events\WebhookDeadLettered;
use Modules\Integrations\Domain\Events\WebhookDelivered;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Shared\Support\Transaction;

/**
 * تسوية نتيجة محاولة إيصال: نجاح (Delivered) أو فشل.
 *
 * عند الفشل تُحسب المحاولة، وتُجدول إعادة المحاولة بعد مهلة من الإعدادات.
 * إذا بلغت المحاولات السقف من config يُعلن الإيصال ميتًا (Dead) ويُنشر
 * WebhookDeadLettered لتدخل بشري.
 *
 * آلة الحالات عند الفشل: Pending → Retrying (مجدولة)، ثم Retrying → Failed
 * (تنتظر قرارًا)، وFailed → Retrying عبر الجدولة أو الإعادة اليدوية، وأي حالة
 * → Dead عند نفاد المحاولات.
 */
final readonly class SettleWebhookDeliveryAction
{
    use TransitionsDeliveryStatus;

    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(
        IntegrationWebhookDelivery $delivery,
        bool $success,
        ?int $responseCode = null,
        ?string $responseBody = null,
    ): IntegrationWebhookDelivery {
        if ($success) {
            return $this->markDelivered($delivery, $responseCode, $responseBody);
        }

        return $this->markAttemptFailed($delivery, $responseCode, $responseBody);
    }

    private function markDelivered(IntegrationWebhookDelivery $delivery, ?int $responseCode, ?string $responseBody): IntegrationWebhookDelivery
    {
        $this->assertDeliveryCanTransition($delivery, DeliveryStatus::Delivered);

        $attempts = $this->nextAttemptNumber($delivery);
        $deliveredAt = CarbonImmutable::now('UTC');

        $this->transaction->run(function () use ($delivery, $attempts, $responseCode, $responseBody, $deliveredAt): void {
            $delivery->update([
                'status' => DeliveryStatus::Delivered,
                'attempts' => $attempts,
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'delivered_at' => $deliveredAt,
                'next_retry_at' => null,
                'failed_at' => null,
            ]);
        });

        $delivery = $delivery->refresh();

        $this->events->dispatch(new WebhookDelivered(
            deliveryId: (string) $delivery->getKey(),
            connectionId: (string) $delivery->connection_id,
            organizationId: $this->organizationId($delivery),
            eventType: (string) $delivery->event_type,
            attempts: $attempts,
            responseCode: (int) ($responseCode ?? 0),
            deliveredAt: $deliveredAt,
        ));

        return $delivery;
    }

    private function markAttemptFailed(IntegrationWebhookDelivery $delivery, ?int $responseCode, ?string $responseBody): IntegrationWebhookDelivery
    {
        $maxAttempts = (int) config('integrations.webhooks.max_attempts');
        $backoffMinutes = (int) config('integrations.webhooks.retry_backoff_minutes');

        $attempts = $this->nextAttemptNumber($delivery);
        $failedAt = CarbonImmutable::now('UTC');
        $exhausted = $attempts >= $maxAttempts;

        $target = match (true) {
            $exhausted => DeliveryStatus::Dead,

            // أول فشل: تُجدول تلقائيًا. فشل بعد إعادة جدولة من Failed: تعود للجدولة.
            $delivery->status !== DeliveryStatus::Retrying => DeliveryStatus::Retrying,

            // محاولة إعادة أرسال فاشلة مع بقاء المحاولات: تنتظر قرارًا صريحًا.
            default => DeliveryStatus::Failed,
        };

        $this->assertDeliveryCanTransition($delivery, $target);

        $this->transaction->run(function () use ($delivery, $target, $attempts, $responseCode, $responseBody, $failedAt, $backoffMinutes): void {
            $delivery->update([
                'status' => $target,
                'attempts' => $attempts,
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'failed_at' => $target === DeliveryStatus::Dead ? $failedAt : null,
                'next_retry_at' => $target === DeliveryStatus::Retrying
                    ? $failedAt->addMinutes($backoffMinutes)
                    : null,
            ]);
        });

        $delivery = $delivery->refresh();

        if ($target === DeliveryStatus::Dead) {
            $this->events->dispatch(new WebhookDeadLettered(
                deliveryId: (string) $delivery->getKey(),
                connectionId: (string) $delivery->connection_id,
                organizationId: $this->organizationId($delivery),
                eventType: (string) $delivery->event_type,
                attempts: $attempts,
                responseCode: $responseCode,
                failedAt: $failedAt,
            ));
        }

        return $delivery;
    }

    private function nextAttemptNumber(IntegrationWebhookDelivery $delivery): int
    {
        return (int) $delivery->attempts + 1;
    }

    private function organizationId(IntegrationWebhookDelivery $delivery): string
    {
        return (string) $delivery->connection()->value('organization_id');
    }
}

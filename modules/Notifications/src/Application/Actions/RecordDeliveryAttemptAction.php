<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationFailed;
use Modules\Notifications\Domain\Events\NotificationSent;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تسجيل محاولة تسليم — يستدعيه المرسِل (worker) بعد كل استدعاء للمزوّد.
 *
 * النجاح يقفل الرسالة بحالة sent. الفشل يعيدها queued إن لم يُستنفد
 * الحد الأقصى للمحاولات من config('notifications.dispatch.max_attempts')،
 * وإلا فتُعلَم failed نهائيًا. لا رقم سياسة داخل هذا الكود.
 */
final readonly class RecordDeliveryAttemptAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param array<string, mixed>|null $providerResponse
     */
    public function execute(
        NotificationOutbox $outbox,
        bool $succeeded,
        ?string $error = null,
        ?array $providerResponse = null,
        ?string $actorId = null,
        bool $retryable = true,
    ): NotificationDeliveryAttempt {
        if ($outbox->status !== OutboxStatus::Sending) {
            throw BusinessRuleViolation::make(
                'notifications.attempt_not_recordable',
                'notifications::errors.attempt_not_recordable',
                ['status' => $outbox->status->label()],
            );
        }

        $maxRetries = max(0, (int) config('notifications.delivery.max_retries'));

        [$attempt, $outbox] = $this->transaction->run(function () use (
            $outbox,
            $succeeded,
            $error,
            $providerResponse,
            $maxRetries,
            $retryable,
        ): array {
            /** @var NotificationOutbox $current */
            $current = NotificationOutbox::query()->lockForUpdate()->findOrFail($outbox->getKey());

            if ($current->status !== OutboxStatus::Sending) {
                throw BusinessRuleViolation::make(
                    'notifications.attempt_not_recordable',
                    'notifications::errors.attempt_not_recordable',
                    ['status' => $current->status->label()],
                );
            }

            $nextNumber = $current->attempts + 1;
            $attemptNumber = (int) NotificationDeliveryAttempt::query()
                ->where('outbox_id', $current->id)
                ->max('attempt_number') + 1;

            // هوية الرسالة وحالة المزوّد وسبب الفشل تُستخرج من ردّ البوابة
            // إلى أعمدة صريحة لتظل قابلة للبحث والتتبّع دون قراءة jsonb.
            $externalMessageId = self::stringValue($providerResponse, 'external_message_id');
            $providerStatus = $succeeded
                ? (self::stringValue($providerResponse, 'status') ?? 'accepted')
                : self::stringValue($providerResponse, 'status');
            $failureReason = $succeeded
                ? null
                : ($error ?? self::stringValue($providerResponse, 'failure_reason'));

            $attempt = NotificationDeliveryAttempt::query()->create([
                'organization_id' => $current->organization_id,
                'outbox_id' => $current->id,
                'attempt_number' => $attemptNumber,
                'attempted_at' => CarbonImmutable::now('UTC'),
                'provider_response' => $providerResponse,
                'external_message_id' => $externalMessageId,
                'succeeded' => $succeeded,
                'retryable' => $succeeded ? null : $retryable,
                'error' => $error,
            ]);

            $current->forceFill([
                'attempts' => $nextNumber,
                'last_error' => $succeeded ? null : $error,
                'last_error_retryable' => $succeeded ? null : $retryable,
                'external_message_id' => $externalMessageId ?? ($succeeded ? null : $current->external_message_id),
                'provider_status' => $providerStatus ?? ($succeeded ? null : $current->provider_status),
                'failure_reason' => $succeeded ? null : $failureReason,
            ])->save();

            if ($succeeded) {
                $this->transition($current, OutboxStatus::Sent);
                $current->forceFill(['sent_at' => CarbonImmutable::now('UTC')])->save();

                return [$attempt, $current];
            }

            if (!$retryable || $nextNumber > $maxRetries) {
                $this->transition($current, OutboxStatus::Failed);

                return [$attempt, $current];
            }

            $current->forceFill([
                'scheduled_for' => CarbonImmutable::now('UTC')->addSeconds(
                    $this->retryDelaySeconds($nextNumber),
                ),
            ])->save();
            $this->transition($current, OutboxStatus::Queued);

            return [$attempt, $current];
        });

        if ($succeeded) {
            $this->events->dispatch(new NotificationSent(
                outboxId: $outbox->id,
                organizationId: $outbox->organization_id,
                userId: $outbox->user_id,
                category: $outbox->category,
                channel: $this->channelValue($outbox),
                attempts: $outbox->attempts,
                sentAt: CarbonImmutable::instance($outbox->sent_at),
                actorId: $actorId,
                correlationId: $outbox->correlation_id,
            ));
        } elseif ($outbox->status === OutboxStatus::Failed) {
            $this->events->dispatch(new NotificationFailed(
                outboxId: $outbox->id,
                organizationId: $outbox->organization_id,
                userId: $outbox->user_id,
                category: $outbox->category,
                channel: $this->channelValue($outbox),
                attempts: $outbox->attempts,
                error: $error,
                actorId: $actorId,
                correlationId: $outbox->correlation_id,
            ));
        }

        return $attempt;
    }

    private function transition(NotificationOutbox $outbox, OutboxStatus $target): void
    {
        $current = $outbox->status;

        if (!$current->canTransitionTo($target)) {
            throw BusinessRuleViolation::make(
                'notifications.invalid_status_transition',
                'notifications::errors.invalid_status_transition',
                ['from' => $current->label(), 'to' => $target->label()],
            );
        }

        $outbox->forceFill(['status' => $target])->save();
    }

    private function channelValue(NotificationOutbox $outbox): string
    {
        return $outbox->channel instanceof \BackedEnum
            ? (string) $outbox->channel->value
            : (string) $outbox->channel;
    }

    private function retryDelaySeconds(int $attemptNumber): int
    {
        $backoff = array_values((array) config('notifications.delivery.backoff_seconds', []));
        $index = max(0, min($attemptNumber - 1, count($backoff) - 1));

        return max(0, (int) ($backoff[$index] ?? 0));
    }

    /**
     * قراءة نصية آمنة من ردّ المزوّد الخام.
     *
     * @param array<string, mixed>|null $response
     */
    private static function stringValue(?array $response, string $key): ?string
    {
        $value = $response[$key] ?? null;

        return is_scalar($value) && trim((string) $value) !== ''
            ? (string) $value
            : null;
    }
}

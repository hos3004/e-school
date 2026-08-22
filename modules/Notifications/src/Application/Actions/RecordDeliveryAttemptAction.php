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
 * النجاح يقفل الرسالة بحالة sent. الفشل يعيدها pending إن لم يُستنفد
 * الحد الأقصى للمحاولات من config('notifications.dispatch.max_attempts')،
 * وإلا فتُعلَم failed نهائيًا. لا رقم سياسة داخل هذا الكود.
 */
final readonly class RecordDeliveryAttemptAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(
        NotificationOutbox $outbox,
        bool $succeeded,
        ?string $error = null,
        ?array $providerResponse = null,
        ?string $actorId = null,
    ): NotificationDeliveryAttempt {
        if (! in_array($outbox->status, [OutboxStatus::Pending, OutboxStatus::Sending, OutboxStatus::Failed], true)) {
            throw BusinessRuleViolation::make(
                'notifications.attempt_not_recordable',
                'notifications::errors.attempt_not_recordable',
                ['status' => $outbox->status->label()],
            );
        }

        $maxAttempts = max(1, (int) config('notifications.dispatch.max_attempts', 3));

        [$attempt, $outbox] = $this->transaction->run(function () use (
            $outbox,
            $succeeded,
            $error,
            $providerResponse,
            $maxAttempts,
        ): array {
            $nextNumber = $outbox->attempts + 1;

            $attempt = NotificationDeliveryAttempt::query()->create([
                'organization_id' => $outbox->organization_id,
                'outbox_id' => $outbox->id,
                'attempt_number' => $nextNumber,
                'attempted_at' => CarbonImmutable::now('UTC'),
                'provider_response' => $providerResponse,
                'succeeded' => $succeeded,
                'error' => $error,
            ]);

            if ($succeeded) {
                $this->transition($outbox, OutboxStatus::Sent);
                $outbox->forceFill(['sent_at' => CarbonImmutable::now('UTC')])->save();

                return [$attempt, $outbox];
            }

            if ($nextNumber >= $maxAttempts) {
                $this->transition($outbox, OutboxStatus::Failed);

                return [$attempt, $outbox];
            }

            $this->transition($outbox, OutboxStatus::Pending);

            return [$attempt, $outbox];
        });

        if ($succeeded) {
            $this->events->dispatch(new NotificationSent(
                outboxId: $outbox->id,
                organizationId: $outbox->organization_id,
                userId: $outbox->user_id,
                category: $outbox->category,
                channel: $outbox->channel,
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
                channel: $outbox->channel,
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

        if (! $current->canTransitionTo($target)) {
            throw BusinessRuleViolation::make(
                'notifications.invalid_status_transition',
                'notifications::errors.invalid_status_transition',
                ['from' => $current->label(), 'to' => $target->label()],
            );
        }

        $outbox->forceFill(['status' => $target])->save();
    }
}

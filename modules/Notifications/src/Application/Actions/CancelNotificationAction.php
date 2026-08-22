<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Events\NotificationCancelled;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إلغاء رسالة في الانتظار قبل إرسالها — للإدارة أو لنظام آخر.
 */
final readonly class CancelNotificationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(NotificationOutbox $outbox, string $reason, ?string $actorId = null): NotificationOutbox
    {
        if (!$outbox->status->canTransitionTo(OutboxStatus::Cancelled)) {
            throw BusinessRuleViolation::make(
                'notifications.not_cancellable',
                'notifications::errors.not_cancellable',
                ['status' => $outbox->status->label()],
            );
        }

        $this->transaction->run(function () use ($outbox): void {
            $outbox->forceFill(['status' => OutboxStatus::Cancelled])->save();
        });

        $this->events->dispatch(new NotificationCancelled(
            outboxId: $outbox->id,
            organizationId: $outbox->organization_id,
            userId: $outbox->user_id,
            category: $outbox->category,
            channel: $outbox->channel instanceof \BackedEnum
                ? (string) $outbox->channel->value
                : (string) $outbox->channel,
            reason: $reason,
            actorId: $actorId,
            correlationId: $outbox->correlation_id,
        ));

        return $outbox;
    }
}

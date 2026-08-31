<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Audit\Domain\Contracts\AuditRecorder;
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
        private AuditRecorder $audit,
    ) {}

    public function execute(NotificationOutbox $outbox, string $reason, ?string $actorId = null): NotificationOutbox
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'notifications.cancel_reason_required',
                'notifications::errors.cancel_reason_required',
            );
        }

        $outbox = $this->transaction->run(function () use ($outbox, $reason, $actorId): NotificationOutbox {
            /** @var NotificationOutbox $current */
            $current = NotificationOutbox::query()->lockForUpdate()->findOrFail($outbox->getKey());
            if (!$current->status->canTransitionTo(OutboxStatus::Cancelled)) {
                throw BusinessRuleViolation::make(
                    'notifications.not_cancellable',
                    'notifications::errors.not_cancellable',
                    ['status' => $current->status->label()],
                );
            }

            $oldStatus = $current->status->value;
            $current->forceFill(['status' => OutboxStatus::Cancelled])->save();
            $this->audit->record(
                organizationId: (string) $current->organization_id,
                actorId: $actorId,
                actorType: $actorId === null ? 'system' : 'user',
                action: 'notifications.cancelled',
                auditableType: 'notification_outbox',
                auditableId: (string) $current->getKey(),
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => OutboxStatus::Cancelled->value],
                reason: $reason,
            );

            return $current;
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

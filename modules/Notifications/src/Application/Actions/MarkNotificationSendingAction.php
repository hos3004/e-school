<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * حجز رسالة بوسع المرسِل قبل الاتصال بالمزوّد.
 *
 * الحجز يمنع تسليمًا مزدوجًا لو التقط المجدول نفس الرسالة أكثر من مرة:
 * أول worker يمسكها يجعلها sending، والبقية تُرفض.
 */
final readonly class MarkNotificationSendingAction
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    public function execute(NotificationOutbox $outbox): NotificationOutbox
    {
        if (!$outbox->status->canTransitionTo(OutboxStatus::Sending)) {
            throw BusinessRuleViolation::make(
                'notifications.not_dispatchable',
                'notifications::errors.not_dispatchable',
                ['status' => $outbox->status->label()],
            );
        }

        $updated = $this->transaction->run(fn (): int => NotificationOutbox::query()
            ->whereKey($outbox->getKey())
            ->where('status', OutboxStatus::Queued)
            ->where('scheduled_for', '<=', now('UTC'))
            ->update(['status' => OutboxStatus::Sending->value]));

        if ($updated === 0) {
            throw BusinessRuleViolation::make(
                'notifications.already_claimed',
                'notifications::errors.already_claimed',
            );
        }

        return $outbox->refresh();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إعادة جدولة رسالة فشلت نهائيًا — قرار إداري يدوي.
 *
 * يعيد الحالة إلى pending دون مسح سجل المحاولات؛ عدد المحاولات يظل
 * محفوظًا في قيود التسليم للتدقيق.
 */
final readonly class RetryNotificationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(NotificationOutbox $outbox, ?string $actorId = null): NotificationOutbox
    {
        if ($outbox->status !== OutboxStatus::Failed) {
            throw BusinessRuleViolation::make(
                'notifications.not_retryable',
                'notifications::errors.not_retryable',
                ['status' => $outbox->status->label()],
            );
        }

        $this->transaction->run(function () use ($outbox): void {
            $outbox->forceFill(['status' => OutboxStatus::Pending])->save();
        });

        return $outbox;
    }
}

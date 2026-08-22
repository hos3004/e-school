<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إعادة جدولة رسالة فشلت نهائيًا — قرار إداري يدوي.
 *
 * يعيد الحالة إلى queued ويبدأ دورة محاولات جديدة. سجل المحاولات السابق
 * يبقى محفوظًا في notification_delivery_attempts للتدقيق.
 */
final readonly class RetryNotificationAction
{
    public function __construct(
        private Transaction $transaction,
    ) {}

    public function execute(NotificationOutbox $outbox, ?string $actorId = null): NotificationOutbox
    {
        return $this->transaction->run(function () use ($outbox): NotificationOutbox {
            /** @var NotificationOutbox $current */
            $current = NotificationOutbox::query()->lockForUpdate()->findOrFail($outbox->getKey());

            if (!$current->status->canTransitionTo(OutboxStatus::Queued)) {
                throw BusinessRuleViolation::make(
                    'notifications.not_retryable',
                    'notifications::errors.not_retryable',
                    ['status' => $current->status->label()],
                );
            }

            $current->forceFill([
                'status' => OutboxStatus::Queued,
                'attempts' => 0,
                'last_error' => null,
                'last_error_retryable' => null,
                'scheduled_for' => now('UTC'),
            ])->save();

            return $current;
        });
    }
}

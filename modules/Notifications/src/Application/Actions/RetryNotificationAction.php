<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
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
        return $this->transaction->run(function () use ($outbox, $actorId): NotificationOutbox {
            /** @var NotificationOutbox $current */
            $current = NotificationOutbox::query()->lockForUpdate()->findOrFail($outbox->getKey());

            if (!$current->status->canTransitionTo(OutboxStatus::Queued)) {
                throw BusinessRuleViolation::make(
                    'notifications.not_retryable',
                    'notifications::errors.not_retryable',
                    ['status' => $current->status->label()],
                );
            }

            // الجدولة الآلية لا تعيد أخطاء دائمة؛ الإدارة وحدها قد تعيدها
            // يدويًا بعد إصلاح الرقم أو القالب أو الإعداد المسبّب للفشل.
            if ($actorId === null && $current->last_error_retryable !== true) {
                throw BusinessRuleViolation::make(
                    'notifications.failure_not_retryable',
                    'notifications::errors.failure_not_retryable',
                );
            }

            $changes = [
                'status' => OutboxStatus::Queued,
                'attempts' => 0,
                'last_error' => null,
                'last_error_retryable' => null,
                'scheduled_for' => now('UTC'),
            ];

            if ($actorId !== null) {
                $changes['last_manual_retry_by'] = $actorId;
                $changes['last_manual_retry_at'] = CarbonImmutable::now('UTC');
            }

            $current->forceFill($changes)->save();

            return $current;
        });
    }

    /**
     * إعادة إرسال إدارية: تثبّت الفاعل أولًا ثم تدفع الرسالة إلى queue.
     *
     * المحاولات السابقة لا تُحذف، ولذلك يسجّل الـworker المحاولة التالية
     * برقم تاريخي جديد في notification_delivery_attempts.
     */
    public function executeManually(NotificationOutbox $outbox, string $actorId): NotificationOutbox
    {
        if (trim($actorId) === '') {
            throw BusinessRuleViolation::make(
                'notifications.manual_retry_actor_required',
                'notifications::errors.manual_retry_actor_required',
            );
        }

        $retried = $this->execute($outbox, $actorId);

        SendQueuedNotification::dispatch($retried->id)
            ->onQueue((string) config('notifications.delivery.queue'));

        return $retried;
    }
}

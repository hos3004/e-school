<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Console;

use Illuminate\Console\Command;
use Modules\Notifications\Application\Actions\RetryNotificationAction;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Support\BusinessRuleViolation;

/**
 * إعادة تشغيل الرسائل الفاشلة نهائيًا خلال نافذة زمنية.
 *
 * لا يقرر هنا كم محاولة أو بأي تأخير — كل ذلك من config؛ الأمر يعيد
 * الفاشلة إلى queued ويترك مهمة الإرسال الخلفية تكمل الدورة كاملة
 * (بما فيها backoff وحد المحاولات) دون أي رقم سياسة في هذا الملف.
 */
final class RetryFailedNotifications extends Command
{
    protected $signature = 'notifications:retry-failed
                            {--limit= : أقصى عدد رسائل تُعاد في التشغيلة الواحدة}';

    protected $description = 'إعادة جدولة الإشعارات الفاشلة من صندوق الصادر لمحاولات تسليم جديدة.';

    public function handle(RetryNotificationAction $retryNotification): int
    {
        $configuredLimit = (int) config('notifications.delivery.retry_batch_size');
        $limit = $this->option('limit') === null
            ? max(1, $configuredLimit)
            : max(1, (int) $this->option('limit'));
        $queued = 0;

        NotificationOutbox::query()
            ->where('status', OutboxStatus::Failed)
            ->where('last_error_retryable', true)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (NotificationOutbox $outbox) use (&$queued, $retryNotification): void {
                try {
                    $retryNotification->execute($outbox);
                } catch (BusinessRuleViolation) {
                    // تغيّرت الحالة بعد الاستعلام وقبل القفل؛ عامل آخر سبقنا.
                    return;
                }

                SendQueuedNotification::dispatch($outbox->id)
                    ->onQueue((string) config('notifications.delivery.queue'));

                $queued++;
            });

        $this->info(__('notifications::messages.retry_failed_done', ['count' => $queued]));

        return self::SUCCESS;
    }
}

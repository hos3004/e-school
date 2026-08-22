<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Console;

use Illuminate\Console\Command;
use Modules\Notifications\Application\Jobs\SendQueuedNotification;
use Modules\Notifications\Domain\Models\NotificationOutbox;

/**
 * المجدول: يلتقط سطور الصندوق المستحقة (queued وبلغ موعدها) ويوزّع
 * مهمة إرسال مستقلة لكل سطر — فشل سطر لا يوقف بقية السطور.
 */
final class DispatchDueNotifications extends Command
{
    protected $signature = 'notifications:dispatch-due
                            {--limit= : أقصى عدد سطور تُوزَّع في التشغيلة الواحدة}';

    protected $description = 'توزيع الإشعارات المستحقة من صندوق الصادر إلى مهام الإرسال الخلفية.';

    public function handle(): int
    {
        $configuredLimit = (int) config('notifications.delivery.dispatch_batch_size');
        $limit = $this->option('limit') === null
            ? max(1, $configuredLimit)
            : max(1, (int) $this->option('limit'));

        $dueIds = NotificationOutbox::query()
            ->due()
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->pluck('id');

        foreach ($dueIds as $outboxId) {
            SendQueuedNotification::dispatch((string) $outboxId)
                ->onQueue((string) config('notifications.delivery.queue'));
        }

        $this->info(__('notifications::messages.dispatch_due_done', ['count' => $dueIds->count()]));

        return self::SUCCESS;
    }
}

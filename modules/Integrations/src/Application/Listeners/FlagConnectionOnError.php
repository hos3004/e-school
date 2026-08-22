<?php

declare(strict_types=1);

namespace Modules\Integrations\Application\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Events\WebhookDeadLettered;
use Modules\Integrations\Domain\Models\IntegrationConnection;

/**
 * عند نفاد محاولات إيصال صادر تُعلَّم حالة الاتصال نفسه بالخطأ حتى
 * يلاحظ المسؤول قبل تراكم الرسائل الميتة.
 *
 * الانتقال يمر عبر canTransitionTo — وإذا لم يكن مسموحًا يُتجاهل بصمت
 * لأن الحدث لا يملك قرار تغيير الحالة، بل إشعارًا بها.
 */
final class FlagConnectionOnError implements ShouldQueue
{
    public function handle(WebhookDeadLettered $event): void
    {
        $connection = IntegrationConnection::query()->find($event->connectionId);

        if ($connection === null) {
            return;
        }

        if ($connection->status->isTerminal()) {
            return;
        }

        if (!$connection->status->canTransitionTo(ConnectionStatus::Error)) {
            return;
        }

        $connection->update([
            'status' => ConnectionStatus::Error,
            'last_error_at' => CarbonImmutable::now('UTC'),
            'last_error_message' => __('integrations::messages.dead_letter_flag', [
                'type' => $event->eventType,
                'attempts' => $event->attempts,
            ]),
        ]);
    }
}

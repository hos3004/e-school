<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Queries;

use Modules\Notifications\Domain\Contracts\NotificationAdministrationQueries;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\ValueObjects\NotificationAdministrationData;

final readonly class NotificationAdministrationQueryService implements NotificationAdministrationQueries
{
    public function forSession(string $organizationId, string $sessionId): array
    {
        return NotificationOutbox::query()
            ->forOrganization($organizationId)
            ->where('payload->session_id', $sessionId)
            ->latest('created_at')
            ->limit(max(1, min((int) config('notifications.admin_hub.max_items', 100), 200)))
            ->get()
            ->map(static fn (NotificationOutbox $outbox): NotificationAdministrationData => new NotificationAdministrationData(
                id: (string) $outbox->getKey(),
                userId: (string) $outbox->user_id,
                category: (string) $outbox->category,
                channel: (string) $outbox->channel,
                status: $outbox->status->value,
                attempts: (int) $outbox->attempts,
                scheduledFor: $outbox->scheduled_for->toIso8601String(),
                sentAt: $outbox->sent_at?->toIso8601String(),
                readAt: $outbox->read_at?->toIso8601String(),
                lastError: $outbox->last_error,
            ))
            ->values()
            ->all();
    }
}

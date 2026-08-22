<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;

/** تعليم كل إشعارات المستخدم المسلّمة داخل التطبيق كمقروءة في تحديث ذري واحد. */
final readonly class MarkAllNotificationsAsReadAction
{
    public function execute(string $userId, string $organizationId): int
    {
        $readAt = CarbonImmutable::now('UTC');

        return NotificationOutbox::query()
            ->forOrganization($organizationId)
            ->forUser($userId)
            ->where('channel', Channel::InApp->value)
            ->where('status', OutboxStatus::Sent)
            ->whereNull('read_at')
            ->update([
                'read_at' => $readAt,
                'updated_at' => $readAt,
            ]);
    }
}

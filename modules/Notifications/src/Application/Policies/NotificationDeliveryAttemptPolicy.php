<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Policies;

use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;

/**
 * سياسة محاولات التسليم — قراءة فقط (سجل تدقيق تاريخي).
 */
final class NotificationDeliveryAttemptPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('notifications.attempt.view_any');
    }

    public function view($user, NotificationDeliveryAttempt $attempt): bool
    {
        return $user->can('notifications.attempt.view')
            && $attempt->organization_id === $user->organization_id;
    }
}

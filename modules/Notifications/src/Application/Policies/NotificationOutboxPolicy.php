<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Policies;

use Modules\Notifications\Domain\Models\NotificationOutbox;

/**
 * سياسة صندوق الإرسال — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات notifications.<resource>.<action> من
 * مصفوفة الصلاحيات، مع مقارنة ملكية السجل/المؤسسة حيثما أمكن.
 */
final class NotificationOutboxPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('notifications.outbox.view_any');
    }

    public function view($user, NotificationOutbox $outbox): bool
    {
        return $user->can('notifications.outbox.view')
            && $outbox->organization_id === $user->organization_id;
    }

    public function viewOwn($user, NotificationOutbox $outbox): bool
    {
        return $outbox->user_id === $user->id;
    }

    public function create($user): bool
    {
        return $user->can('notifications.outbox.create');
    }

    public function update($user, NotificationOutbox $outbox): bool
    {
        return $user->can('notifications.outbox.update')
            && $outbox->organization_id === $user->organization_id;
    }

    public function delete($user, NotificationOutbox $outbox): bool
    {
        return $user->can('notifications.outbox.delete')
            && $outbox->organization_id === $user->organization_id;
    }

    /** إلغاء رسالة في الانتظار. */
    public function cancel($user, NotificationOutbox $outbox): bool
    {
        return $user->can('notifications.outbox.cancel')
            && $outbox->organization_id === $user->organization_id;
    }

    /** إعادة محاولة رسالة فاشلة. */
    public function retry($user, NotificationOutbox $outbox): bool
    {
        return $user->can('notifications.outbox.retry')
            && $outbox->organization_id === $user->organization_id;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Policies;

use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;

/**
 * سياسة صندوق الإرسال — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * القراءة الذاتية تعتمد الملكية، وإجراءات الإدارة تستخدم settings.manage
 * من مصفوفة الصلاحيات مع مقارنة المؤسسة دائمًا.
 */
final class NotificationOutboxPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('settings.manage');
    }

    public function view(mixed $user, NotificationOutbox $outbox): bool
    {
        return $user->can('settings.manage')
            && $outbox->organization_id === $user->organization_id;
    }

    public function viewOwn(mixed $user, NotificationOutbox $outbox): bool
    {
        return $outbox->user_id === (string) $user->getAuthIdentifier()
            && $outbox->organization_id === $user->organization_id;
    }

    /** قائمة الجرس تخص المستخدم المصادق عليه فقط. */
    public function listOwn(mixed $user): bool
    {
        return (string) $user->getAuthIdentifier() !== ''
            && (string) $user->organization_id !== '';
    }

    public function markAsRead(mixed $user, NotificationOutbox $outbox): bool
    {
        return $this->viewOwn($user, $outbox)
            && $outbox->channel === Channel::InApp->value
            && $outbox->status === OutboxStatus::Sent;
    }

    public function markAllAsRead(mixed $user): bool
    {
        return $this->listOwn($user);
    }

    public function create(mixed $user): bool
    {
        return $user->can('notifications.outbox.create');
    }

    public function update(mixed $user, NotificationOutbox $outbox): bool
    {
        return $user->can('notifications.outbox.update')
            && $outbox->organization_id === $user->organization_id;
    }

    public function delete(mixed $user, NotificationOutbox $outbox): bool
    {
        return $user->can('notifications.outbox.delete')
            && $outbox->organization_id === $user->organization_id;
    }

    /** إلغاء رسالة في الانتظار. */
    public function cancel(mixed $user, NotificationOutbox $outbox): bool
    {
        return $user->can('notifications.outbox.cancel')
            && $outbox->organization_id === $user->organization_id;
    }

    /** إعادة محاولة رسالة فاشلة. */
    public function retry(mixed $user, NotificationOutbox $outbox): bool
    {
        return $user->can('settings.manage')
            && $outbox->organization_id === $user->organization_id;
    }
}

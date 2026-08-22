<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Policies;

use Modules\Notifications\Domain\Models\NotificationPreference;

/**
 * سياسة تفضيلات الإشعارات.
 *
 * المستخدم يدير تفضيلاته دائمًا (updateOwn)، والإدارة تدير تفضيلات
 * مؤسستها عبر بوابة الصلاحيات notifications.preference.*.
 */
final class NotificationPreferencePolicy
{
    public function viewAny(mixed $user): bool
    {
        return $user->can('notifications.preference.view_any');
    }

    public function view(mixed $user, NotificationPreference $preference): bool
    {
        return $user->can('notifications.preference.view')
            && $preference->organization_id === $user->organization_id;
    }

    public function viewOwn(mixed $user, NotificationPreference $preference): bool
    {
        return $preference->user_id === $user->id;
    }

    public function create(mixed $user): bool
    {
        return $user->can('notifications.preference.create');
    }

    public function update(mixed $user, NotificationPreference $preference): bool
    {
        return $user->can('notifications.preference.update')
            && $preference->organization_id === $user->organization_id;
    }

    /** كل مستخدم يعدّل تفضيلاته هو فقط. */
    public function updateOwn(mixed $user, NotificationPreference $preference): bool
    {
        return $preference->user_id === $user->id;
    }

    public function delete(mixed $user, NotificationPreference $preference): bool
    {
        return $user->can('notifications.preference.delete')
            && $preference->organization_id === $user->organization_id;
    }
}

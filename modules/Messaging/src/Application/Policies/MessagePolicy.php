<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Modules\Messaging\Domain\Models\Message;

/**
 * سياسة الرسائل — الكاتب وحده يعدّل، والمشرف فقط يسوم.
 */
final class MessagePolicy
{
    public function viewAny($user): bool
    {
        return $user->can('messaging.message.view_any');
    }

    public function view($user, Message $message): bool
    {
        return $user->can('messaging.message.view')
            && $message->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('messaging.message.create');
    }

    public function update($user, Message $message): bool
    {
        return $user->can('messaging.message.update')
            && $message->organization_id === $user->organization_id
            && (string) $message->user_id === $user->getAuthIdentifier();
    }

    public function delete($user, Message $message): bool
    {
        return $user->can('messaging.message.delete')
            && $message->organization_id === $user->organization_id;
    }

    public function flag($user, Message $message): bool
    {
        return $user->can('messaging.message.flag')
            && $message->organization_id === $user->organization_id;
    }
}

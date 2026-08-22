<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Messaging\Domain\Models\Message;

/**
 * سياسة الرسائل — الكاتب وحده يعدّل، والمشرف فقط يسوم.
 */
final class MessagePolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('message.send') || $user->can('message.moderate');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, Message $message): bool
    {
        return ($user->can('message.send') || $user->can('message.moderate'))
            && $message->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('message.send');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, Message $message): bool
    {
        return ($user->can('message.send') || $user->can('message.moderate'))
            && $message->organization_id === $user->organization_id
            && (string) $message->user_id === $user->getAuthIdentifier();
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, Message $message): bool
    {
        return $user->can('message.moderate')
            && $message->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function flag(Authenticatable $user, Message $message): bool
    {
        return $user->can('message.send')
            && $message->organization_id === $user->organization_id;
    }
}

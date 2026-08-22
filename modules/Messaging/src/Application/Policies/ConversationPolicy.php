<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;

/**
 * سياسة المحادثات — لا فحص لأسماء الأدوار إطلاقًا.
 */
final class ConversationPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('messaging.conversation.view_any');
    }

    public function view($user, Conversation $conversation): bool
    {
        if ($conversation->organization_id !== $user->organization_id) {
            return false;
        }

        return $user->can('messaging.conversation.view')
            || $this->isParticipant($user->getAuthIdentifier(), (string) $conversation->id);
    }

    public function create($user): bool
    {
        return $user->can('messaging.conversation.create');
    }

    public function update($user, Conversation $conversation): bool
    {
        return $user->can('messaging.conversation.update')
            && $conversation->organization_id === $user->organization_id;
    }

    public function delete($user, Conversation $conversation): bool
    {
        return $user->can('messaging.conversation.delete')
            && $conversation->organization_id === $user->organization_id;
    }

    public function sendMessage($user, Conversation $conversation): bool
    {
        if ($conversation->organization_id !== $user->organization_id) {
            return false;
        }

        return $user->can('messaging.message.create')
            && $this->isParticipant($user->getAuthIdentifier(), (string) $conversation->id);
    }

    private function isParticipant(string $userId, string $conversationId): bool
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }
}

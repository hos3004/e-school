<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;

/**
 * سياسة المحادثات — لا فحص لأسماء الأدوار إطلاقًا.
 */
final class ConversationPolicy
{
    public function __construct(
        private readonly AccessControlQuerier $accessControl,
    ) {}

    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('message.send') || $user->can('message.moderate');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, Conversation $conversation): bool
    {
        if ($conversation->organization_id !== $user->organization_id) {
            return false;
        }

        // صلاحية الإشراف الصريحة تتقدم على تصنيف الحساب؛ فقد يحمل المشرف
        // صلاحيات متابعة إضافية، لكن ذلك لا يجعله ولي أمر في هذا السياق.
        if ($this->hasPermission($user, 'classroom.moderate')
            || $this->hasPermission($user, 'message.moderate')) {
            return true;
        }

        // ولي الأمر لا يرى محادثة الطالب والمعلم عبر معرّف مباشر.
        if ($this->hasPermission($user, 'guardian.view')) {
            return false;
        }

        return $this->isParticipant((string) $user->getAuthIdentifier(), (string) $conversation->id);
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('message.send');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, Conversation $conversation): bool
    {
        return $user->can('message.moderate')
            && $conversation->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, Conversation $conversation): bool
    {
        return $user->can('message.moderate')
            && $conversation->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function sendMessage(Authenticatable $user, Conversation $conversation): bool
    {
        if ($conversation->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->can('guardian.view') && !$user->can('message.moderate')) {
            return false;
        }

        return $user->can('message.send')
            && $this->isParticipant((string) $user->getAuthIdentifier(), (string) $conversation->id);
    }

    private function isParticipant(string $userId, string $conversationId): bool
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function hasPermission(Authenticatable $user, string $permission): bool
    {
        $identifier = $user->getAuthIdentifier();

        if (!is_string($identifier) && !is_int($identifier)) {
            return false;
        }

        $modelType = $user instanceof Model ? $user->getMorphClass() : $user::class;

        return $this->accessControl->modelHasPermission(
            $modelType,
            (string) $identifier,
            $permission,
            'web',
        );
    }
}

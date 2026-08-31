<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;

/**
 * سياسة المحادثات — لا فحص لأسماء الأدوار إطلاقًا.
 */
final class ConversationPolicy
{
    public function __construct(
        private readonly AccessControlQuerier $accessControl,
        private readonly ClassAudienceQueries $audience,
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

        $userId = (string) $user->getAuthIdentifier();
        $participantIds = $conversation->participants()
            ->pluck('user_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        // Explicit privacy barrier: even a corrupted/legacy participant row
        // never grants a guardian access to a Student<->Teacher direct thread.
        if ($conversation->type === ConversationType::Direct->value
            && $this->audience->isGuardian((string) $conversation->organization_id, $userId)
            && $this->containsStudentTeacherPair(
                (string) $conversation->organization_id,
                $participantIds,
            )) {
            return false;
        }

        // صلاحية الإشراف الصريحة تتقدم على تصنيف الحساب؛ فقد يحمل المشرف
        // صلاحيات متابعة إضافية، لكن ذلك لا يجعله ولي أمر في هذا السياق.
        if ($this->hasPermission($user, 'classroom.moderate')
            || $this->hasPermission($user, 'message.moderate')) {
            return true;
        }

        return $this->isParticipant($userId, (string) $conversation->id);
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

        return $this->view($user, $conversation)
            && $user->can('message.send')
            && $this->isParticipant((string) $user->getAuthIdentifier(), (string) $conversation->id);
    }

    /**
     * Legacy data may contain an extra participant in a direct conversation.
     * Inspect pairs so that such a row cannot bypass the student-teacher privacy
     * barrier merely because the participant count is no longer exactly two.
     *
     * @param list<string> $participantIds
     */
    private function containsStudentTeacherPair(string $organizationId, array $participantIds): bool
    {
        $ids = array_values(array_unique($participantIds));
        $count = count($ids);

        for ($left = 0; $left < $count; $left++) {
            for ($right = $left + 1; $right < $count; $right++) {
                if ($this->audience->isStudentTeacherConversation(
                    $organizationId,
                    [$ids[$left], $ids[$right]],
                )) {
                    return true;
                }
            }
        }

        return false;
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

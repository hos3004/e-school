<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Enums\ParticipantRole;
use Modules\Messaging\Domain\Events\ConversationCreated;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إنشاء محادثة جديدة مع مشاركيها.
 */
final readonly class CreateConversationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    /**
     * @param list<string> $participantUserIds
     */
    public function execute(
        string $organizationId,
        string $creatorUserId,
        ConversationType $type,
        string $subject,
        array $participantUserIds,
        bool $isModerated = true,
        ?string $relatedType = null,
        ?string $relatedId = null,
    ): Conversation {
        $participants = array_values(array_unique($participantUserIds));

        if (!in_array($creatorUserId, $participants, true)) {
            $participants[] = $creatorUserId;
        }

        $maxParticipants = (int) config('messaging.limits.max_participants');

        if ($maxParticipants > 0 && count($participants) > $maxParticipants) {
            throw BusinessRuleViolation::make(
                'messaging.too_many_participants',
                'messaging::errors.too_many_participants',
                ['max' => $maxParticipants],
            );
        }

        if ($type === ConversationType::Direct && count($participants) > 2) {
            throw BusinessRuleViolation::make(
                'messaging.direct_exceeds_two',
                'messaging::errors.direct_exceeds_two',
            );
        }

        return $this->transaction->run(function () use (
            $organizationId,
            $creatorUserId,
            $type,
            $subject,
            $participants,
            $isModerated,
            $relatedType,
            $relatedId,
        ): Conversation {
            $conversation = new Conversation([
                'organization_id' => $organizationId,
                'subject' => $subject,
                'type' => $type->value,
                'is_moderated' => $isModerated,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'created_by' => $creatorUserId,
                'last_message_at' => null,
                'created_at' => now(),
            ]);
            $conversation->save();

            foreach ($participants as $userId) {
                ConversationParticipant::query()->create([
                    'organization_id' => $organizationId,
                    'conversation_id' => (string) $conversation->id,
                    'user_id' => $userId,
                    'role' => $userId === $creatorUserId
                        ? ParticipantRole::Owner->value
                        : ParticipantRole::Member->value,
                    'joined_at' => now(),
                    'last_read_at' => null,
                    'muted_until' => null,
                ]);
            }

            $this->events->dispatch(new ConversationCreated(
                conversationId: (string) $conversation->id,
                organizationId: $organizationId,
                type: $type->value,
                participantUserIds: $participants,
            ));

            return $conversation;
        });
    }
}

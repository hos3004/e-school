<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Models\Conversation;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/** Starts a direct conversation and its first message atomically. */
final readonly class StartDirectConversationAction
{
    public function __construct(
        private Transaction $transaction,
        private UserAccountDirectory $users,
        private CreateConversationAction $createConversation,
        private SendMessageAction $sendMessage,
    ) {}

    public function execute(
        string $organizationId,
        string $actorUserId,
        string $recipientUserId,
        string $subject,
        string $body,
    ): Conversation {
        $recipient = $this->users->find($organizationId, $recipientUserId);

        if ($recipient === null || $recipient->status !== 'active' || $recipientUserId === $actorUserId) {
            throw BusinessRuleViolation::make(
                'messaging.invalid_recipient',
                'messaging::errors.invalid_recipient',
            );
        }

        return $this->transaction->run(function () use ($organizationId, $actorUserId, $recipientUserId, $subject, $body): Conversation {
            $conversation = $this->createConversation->execute(
                organizationId: $organizationId,
                creatorUserId: $actorUserId,
                type: ConversationType::Direct,
                subject: $subject,
                participantUserIds: [$recipientUserId],
            );

            $this->sendMessage->execute(
                conversation: $conversation,
                senderUserId: $actorUserId,
                body: $body,
            );

            return $conversation->refresh();
        });
    }
}

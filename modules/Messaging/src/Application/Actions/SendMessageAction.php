<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Messaging\Domain\Events\MessageSent;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Modules\Messaging\Domain\Models\Message;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * إرسال رسالة داخل محادثة — للمشاركين فقط.
 */
final readonly class SendMessageAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(
        Conversation $conversation,
        string $senderUserId,
        string $body,
        array $attachments = [],
    ): Message {
        $isParticipant = ConversationParticipant::query()
            ->where('conversation_id', (string) $conversation->id)
            ->where('user_id', $senderUserId)
            ->exists();

        if (! $isParticipant) {
            throw BusinessRuleViolation::make(
                'messaging.not_participant',
                'messaging::errors.not_participant',
            );
        }

        return $this->transaction->run(function () use ($conversation, $senderUserId, $body, $attachments): Message {
            $message = new Message([
                'organization_id' => $conversation->organization_id,
                'conversation_id' => (string) $conversation->id,
                'user_id' => $senderUserId,
                'body' => $body,
                'attachments' => $attachments,
                'is_flagged' => false,
                'flagged_reason' => null,
                'moderated_by' => null,
                'moderated_at' => null,
                'edited_at' => null,
            ]);
            $message->created_at = now();
            $message->save();

            $conversation->forceFill(['last_message_at' => now()])->save();

            $this->events->dispatch(new MessageSent(
                messageId: (string) $message->id,
                conversationId: (string) $conversation->id,
                organizationId: (string) $conversation->organization_id,
                senderUserId: $senderUserId,
            ));

            return $message;
        });
    }
}

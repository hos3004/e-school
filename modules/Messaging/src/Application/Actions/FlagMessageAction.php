<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Messaging\Domain\Events\MessageFlagged;
use Modules\Messaging\Domain\Models\Message;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * وسم رسالة كمخالفة — للمشرفين، مع سبب موثّق.
 */
final readonly class FlagMessageAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Message $message, string $moderatorUserId, string $reason): Message
    {
        if ($message->is_flagged) {
            throw BusinessRuleViolation::make(
                'messaging.message_already_flagged',
                'messaging::errors.message_already_flagged',
            );
        }

        $flagged = $this->transaction->run(function () use ($message, $moderatorUserId, $reason): Message {
            $message->is_flagged = true;
            $message->flagged_reason = $reason;
            $message->moderated_by = $moderatorUserId;
            $message->moderated_at = now();
            $message->save();

            return $message;
        });

        $this->events->dispatch(new MessageFlagged(
            messageId: (string) $flagged->id,
            conversationId: (string) $flagged->conversation_id,
            organizationId: (string) $flagged->organization_id,
            moderatorUserId: $moderatorUserId,
            reason: $reason,
        ));

        return $flagged;
    }
}

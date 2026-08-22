<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Messaging\Domain\Events\MessageEdited;
use Modules\Messaging\Domain\Models\Message;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * تعديل رسالة — للكاتب فقط وضمن نافذة التعديل من الإعدادات.
 */
final readonly class EditMessageAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(Message $message, string $editorUserId, string $newBody): Message
    {
        if ((string) $message->user_id !== $editorUserId) {
            throw BusinessRuleViolation::make(
                'messaging.not_message_author',
                'messaging::errors.not_message_author',
            );
        }

        if ($message->is_flagged) {
            throw BusinessRuleViolation::make(
                'messaging.message_flagged_locked',
                'messaging::errors.message_flagged_locked',
            );
        }

        if ($message->edited_at !== null) {
            throw BusinessRuleViolation::make(
                'messaging.message_already_edited',
                'messaging::errors.message_already_edited',
            );
        }

        $windowMinutes = (int) config('messaging.edit.window_minutes');
        $createdAt = $message->created_at;

        if ($createdAt === null || now()->diffInMinutes($createdAt) > $windowMinutes) {
            throw BusinessRuleViolation::make(
                'messaging.edit_window_expired',
                'messaging::errors.edit_window_expired',
                ['minutes' => $windowMinutes],
            );
        }

        $edited = $this->transaction->run(function () use ($message, $newBody): Message {
            $message->body = $newBody;
            $message->edited_at = now();
            $message->save();

            return $message;
        });

        $this->events->dispatch(new MessageEdited(
            messageId: (string) $edited->id,
            conversationId: (string) $edited->conversation_id,
            organizationId: (string) $edited->organization_id,
            editorUserId: $editorUserId,
        ));

        return $edited;
    }
}

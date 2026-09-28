<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Modules\Messaging\Domain\Models\Message;

/**
 * يحقن في كل Conversation بيانات مشتقّة لا يحملها الجدول نفسه: أسماء
 * المشاركين وآخر رسالة وعدد غير المقروء للمُشاهد الحالي. Notifications
 * وModules الأخرى لا تُستدعى هنا؛ الأسماء عبر UserAccountDirectory فقط.
 *
 * ConversationResource تقرأ هذه القيم من attributes ديناميكية على الموديل،
 * فلا حاجة لتمرير سياق إضافي عبر الـResource نفسه.
 */
final readonly class ConversationSummaryEnricher
{
    public function __construct(
        private UserAccountDirectory $accounts,
    ) {}

    /**
     * @param iterable<int, Conversation> $conversations
     */
    public function attach(iterable $conversations, string $organizationId, string $viewerUserId): void
    {
        /** @var Collection<int, Conversation> $list */
        $list = $conversations instanceof Collection ? $conversations : collect($conversations);

        if ($list->isEmpty()) {
            return;
        }

        $conversationIds = $list->map(static fn (Conversation $c): string => (string) $c->id)->all();

        $participantRows = ConversationParticipant::query()
            ->whereIn('conversation_id', $conversationIds)
            ->get(['conversation_id', 'user_id', 'role', 'last_read_at']);

        $userIds = $participantRows
            ->map(static fn (ConversationParticipant $row): string => (string) $row->user_id)
            ->unique()
            ->values()
            ->all();

        $accounts = $this->accounts->findMany($organizationId, $userIds);

        $participantsByConversation = $participantRows->groupBy(
            static fn (ConversationParticipant $row): string => (string) $row->conversation_id,
        );

        $messagesByConversation = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->orderByDesc('created_at')
            ->get(['conversation_id', 'user_id', 'body', 'created_at'])
            ->groupBy(static fn (Message $message): string => (string) $message->conversation_id);

        foreach ($list as $conversation) {
            $conversationId = (string) $conversation->id;
            $rows = $participantsByConversation->get($conversationId, collect());

            $participants = $rows
                ->map(function (ConversationParticipant $row) use ($accounts): array {
                    $userId = (string) $row->user_id;
                    $account = $accounts[$userId] ?? null;
                    $name = $account !== null ? $account->name : __('messaging::fields.unknown_sender');

                    return [
                        'id' => $userId,
                        'name' => $name,
                        'role' => $row->role,
                    ];
                })
                ->values()
                ->all();

            $viewerRow = $rows->first(
                static fn (ConversationParticipant $row): bool => (string) $row->user_id === $viewerUserId,
            );

            $messages = $messagesByConversation->get($conversationId, collect());
            $lastMessage = $messages->first();

            $unreadCount = 0;

            if ($viewerRow !== null) {
                $lastReadAt = $viewerRow->last_read_at;
                $unreadCount = $messages
                    ->filter(static fn (Message $message): bool => (string) $message->user_id !== $viewerUserId
                        && ($lastReadAt === null || $message->created_at?->gt($lastReadAt)))
                    ->count();
            }

            $conversation->setAttribute('participants_summary', $participants);
            $conversation->setAttribute(
                'last_message_preview',
                $lastMessage instanceof Message ? Str::limit((string) $lastMessage->body, 140) : null,
            );
            $conversation->setAttribute('unread_count_for_viewer', $unreadCount);
        }
    }

    /**
     * @param iterable<int, Message> $messages
     */
    public function attachSenderNames(iterable $messages, string $organizationId): void
    {
        /** @var Collection<int, Message> $list */
        $list = $messages instanceof Collection ? $messages : collect($messages);

        if ($list->isEmpty()) {
            return;
        }

        $userIds = $list
            ->map(static fn (Message $message): string => (string) $message->user_id)
            ->unique()
            ->values()
            ->all();

        $accounts = $this->accounts->findMany($organizationId, $userIds);

        foreach ($list as $message) {
            $account = $accounts[(string) $message->user_id] ?? null;
            $name = $account !== null ? $account->name : __('messaging::fields.unknown_sender');
            $message->setAttribute('sender_name', $name);
        }
    }
}

<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Presentation\Http\Resources\MessageResource;
use Modules\Messaging\Domain\Models\Message;

/**
 * عرض رسائل محادثة.
 */
final class ListConversationMessagesController extends Controller
{
    public function __invoke(Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        $messages = Message::query()
            ->where('conversation_id', (string) $conversation->id)
            ->orderBy('created_at')
            ->get();

        return MessageResource::collection($messages);
    }
}

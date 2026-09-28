<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Symfony\Component\HttpFoundation\Response;

/**
 * تعليم محادثة كمقروءة للمستخدم الحالي — يحرّك last_read_at بتاعه وحده،
 * فلا يمس نسخة أي مشارك آخر ولا يُعتبر "قراءة جماعية" (لا read receipts).
 */
final class MarkConversationReadController extends Controller
{
    public function __invoke(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $userId = (string) $request->user()?->getAuthIdentifier();

        ConversationParticipant::query()
            ->where('conversation_id', (string) $conversation->id)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);

        return response()->noContent();
    }
}

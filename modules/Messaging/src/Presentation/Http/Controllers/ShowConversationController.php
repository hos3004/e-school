<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Application\Services\ConversationSummaryEnricher;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Presentation\Http\Resources\ConversationResource;

/**
 * عرض محادثة واحدة بعد تفويض الوصول على مستوى الكائن نفسه.
 */
final class ShowConversationController extends Controller
{
    public function __invoke(
        Request $request,
        Conversation $conversation,
        ConversationSummaryEnricher $summaries,
    ): ConversationResource {
        Gate::authorize('view', $conversation);

        $userId = (string) $request->user()?->getAuthIdentifier();
        $summaries->attach([$conversation], (string) $conversation->organization_id, $userId);

        return new ConversationResource($conversation);
    }
}

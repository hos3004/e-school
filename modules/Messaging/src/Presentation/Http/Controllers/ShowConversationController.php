<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Presentation\Http\Resources\ConversationResource;

/**
 * عرض محادثة واحدة بعد تفويض الوصول على مستوى الكائن نفسه.
 */
final class ShowConversationController extends Controller
{
    public function __invoke(Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        return new ConversationResource($conversation);
    }
}

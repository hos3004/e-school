<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Presentation\Http\Resources\ConversationResource;

/** Lists only conversations the current actor may actually open. */
final class ListConversationsController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Conversation::class);

        $user = $request->user();
        $userId = (string) $user?->getAuthIdentifier();
        $organizationId = (string) data_get($user, 'organization_id');

        $query = Conversation::query()
            ->forOrganization($organizationId)
            ->when(
                $user?->can('message.moderate') !== true,
                static fn (Builder $conversations): Builder => $conversations->whereHas(
                    'participants',
                    static fn (Builder $participants): Builder => $participants
                        ->where('user_id', $userId)
                        ->where('organization_id', $organizationId),
                ),
            );

        // Resolve record-level privacy before pagination so denied legacy rows
        // cannot shorten a page or influence its cursor.
        $allowedIds = (clone $query)
            ->get()
            ->filter(static fn (Conversation $conversation): bool => Gate::allows('view', $conversation))
            ->modelKeys();

        $conversations = $query
            ->whereKey($allowedIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate();

        return ConversationResource::collection($conversations);
    }
}

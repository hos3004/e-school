<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Presentation\Http\Resources\ConversationResource;

/** Lists only conversations the current actor may actually open. */
final class ListConversationsController extends Controller
{
    public function __invoke(
        Request $request,
        ClassAudienceQueries $audience,
    ): AnonymousResourceCollection {
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

        $query = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // Participant and moderator queries already encode the same tenant and
        // record access enforced by the policy. Keep the common path entirely
        // database-paginated instead of materializing every allowed ID.
        if (!$audience->isGuardian($organizationId, $userId)) {
            return ConversationResource::collection($query->paginate());
        }

        // Legacy data may contain a guardian participant row on a private
        // Student<->Teacher direct thread. That exception still needs the
        // record policy before pagination. Stream it in bounded chunks and
        // retain only the requested page rather than an unbounded ID array.
        $page = max($request->integer('page', 1), 1);
        $perPage = $query->getModel()->getPerPage();
        $offset = ($page - 1) * $perPage;
        $total = 0;
        $items = [];

        foreach ($query->lazy(200) as $conversation) {
            if (!Gate::allows('view', $conversation)) {
                continue;
            }

            if ($total >= $offset && count($items) < $perPage) {
                $items[] = $conversation;
            }

            $total++;
        }

        $conversations = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'page',
            ],
        );

        return ConversationResource::collection($conversations);
    }
}

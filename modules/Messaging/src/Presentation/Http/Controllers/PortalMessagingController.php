<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Messaging\Domain\Models\Conversation;

final class PortalMessagingController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Conversation::class);

        return Inertia::render('Shared/Messaging/Inbox');
    }

    public function create(): Response
    {
        Gate::authorize('create', Conversation::class);

        return Inertia::render('Shared/Messaging/Create');
    }

    public function show(Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        return Inertia::render('Shared/Messaging/Show', [
            'conversationId' => (string) $conversation->getKey(),
            'subject' => (string) $conversation->subject,
        ]);
    }
}

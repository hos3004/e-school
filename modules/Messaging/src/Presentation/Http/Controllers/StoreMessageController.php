<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Application\Actions\SendMessageAction;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Presentation\Http\Requests\StoreMessageRequest;
use Modules\Messaging\Presentation\Http\Resources\MessageResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * إرسال رسالة داخل محادثة.
 */
final class StoreMessageController extends Controller
{
    public function __construct(
        private readonly SendMessageAction $action,
    ) {}

    public function __invoke(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        $message = $this->action->execute(
            conversation: $conversation,
            senderUserId: (string) $request->user()->getAuthIdentifier(),
            body: $request->string('body')->toString(),
            attachments: array_values($request->array('attachments')),
        );

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

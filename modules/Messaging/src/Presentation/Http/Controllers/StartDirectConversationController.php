<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Messaging\Application\Actions\StartDirectConversationAction;
use Modules\Messaging\Application\Services\ConversationSummaryEnricher;
use Modules\Messaging\Presentation\Http\Requests\StartDirectConversationRequest;
use Modules\Messaging\Presentation\Http\Resources\ConversationResource;
use Symfony\Component\HttpFoundation\Response;

final class StartDirectConversationController extends Controller
{
    public function __construct(
        private readonly StartDirectConversationAction $action,
    ) {}

    public function __invoke(StartDirectConversationRequest $request, ConversationSummaryEnricher $summaries): JsonResponse
    {
        $organizationId = (string) $request->user()->organization_id;
        $actorId = (string) $request->user()->getAuthIdentifier();

        $conversation = $this->action->execute(
            organizationId: $organizationId,
            actorUserId: $actorId,
            recipientUserId: $request->string('recipient_user_id')->toString(),
            subject: $request->string('subject')->toString(),
            body: $request->string('body')->toString(),
        );

        $summaries->attach([$conversation], $organizationId, $actorId);

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Messaging\Application\Actions\CreateConversationAction;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Presentation\Http\Requests\StoreConversationRequest;
use Modules\Messaging\Presentation\Http\Resources\ConversationResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * إنشاء محادثة.
 */
final class StoreConversationController extends Controller
{
    public function __construct(
        private readonly CreateConversationAction $action,
    ) {}

    public function __invoke(StoreConversationRequest $request): JsonResponse
    {
        /** @var string $organizationId */
        $organizationId = $request->user()->organization_id;

        $conversation = $this->action->execute(
            organizationId: $organizationId,
            creatorUserId: (string) $request->user()->getAuthIdentifier(),
            type: ConversationType::from($request->string('type')->toString()),
            subject: $request->string('subject')->toString(),
            participantUserIds: $request->array('participant_user_ids'),
            isModerated: (bool) $request->boolean('is_moderated', true),
            relatedType: $request->filled('related_type')
                ? $request->string('related_type')->toString()
                : null,
            relatedId: $request->filled('related_id')
                ? $request->string('related_id')->toString()
                : null,
        );

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

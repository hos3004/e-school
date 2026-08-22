<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Messaging\Application\Actions\PublishWallPostAction;
use Modules\Messaging\Presentation\Http\Requests\StoreWallPostRequest;
use Modules\Messaging\Presentation\Http\Resources\ClassWallPostResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * نشر منشور على حائط الصف.
 */
final class StoreWallPostController extends Controller
{
    public function __construct(
        private readonly PublishWallPostAction $action,
    ) {}

    public function __invoke(StoreWallPostRequest $request): JsonResponse
    {
        /** @var string $organizationId */
        $organizationId = $request->user()->organization_id;

        $post = $this->action->execute(
            organizationId: $organizationId,
            groupId: $request->string('group_id')->toString(),
            authorUserId: (string) $request->user()->getAuthIdentifier(),
            body: $request->string('body')->toString(),
            attachments: array_values($request->array('attachments')),
            isPinned: (bool) $request->boolean('is_pinned', false),
        );

        return (new ClassWallPostResource($post))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

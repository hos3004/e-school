<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Application\Actions\AddWallCommentAction;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Modules\Messaging\Presentation\Http\Requests\StoreWallCommentRequest;
use Modules\Messaging\Presentation\Http\Resources\ClassWallCommentResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * إضافة تعليق على منشور حائط الصف.
 */
final class StoreWallCommentController extends Controller
{
    public function __construct(
        private readonly AddWallCommentAction $action,
    ) {}

    public function __invoke(StoreWallCommentRequest $request, ClassWallPost $post): JsonResponse
    {
        Gate::authorize('view', $post);

        $comment = $this->action->execute(
            post: $post,
            commenterUserId: (string) $request->user()->getAuthIdentifier(),
            body: $request->string('body')->toString(),
        );

        return (new ClassWallCommentResource($comment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

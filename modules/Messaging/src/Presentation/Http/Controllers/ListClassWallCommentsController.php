<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Messaging\Application\Services\ClassWallSummaryEnricher;
use Modules\Messaging\Domain\Models\ClassWallComment;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Modules\Messaging\Presentation\Http\Resources\ClassWallCommentResource;

/**
 * تعليقات منشور — من يقدر يرى المنشور يقدر يرى تعليقاته، نفس تفويض العرض.
 */
final class ListClassWallCommentsController extends Controller
{
    public function __invoke(ClassWallPost $post, ClassWallSummaryEnricher $summaries): AnonymousResourceCollection
    {
        Gate::authorize('view', $post);

        $comments = ClassWallComment::query()
            ->forPost((string) $post->id)
            ->orderBy('created_at')
            ->get();

        $summaries->attachToComments($comments, (string) $post->organization_id);

        return ClassWallCommentResource::collection($comments);
    }
}

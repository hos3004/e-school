<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Messaging\Application\Services\ClassWallSummaryEnricher;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Modules\Messaging\Presentation\Http\Resources\ClassWallPostResource;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * منشورات حائط مجموعة — المثبّت أولًا ثم الأحدث. نفس شرط الوصول المستخدم
 * في ClassWallPostPolicy::view() بالضبط، مطبّقًا على مستوى المجموعة كلها
 * بدل منشور واحد، لأنه لا يوجد سجل منشور بعد لنفوّض عليه.
 */
final class ListClassWallPostsController
{
    public function __invoke(
        Request $request,
        string $group,
        ClassAudienceQueries $audience,
        ClassWallSummaryEnricher $summaries,
    ): AnonymousResourceCollection {
        $user = $request->user();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $userId = (string) $user?->getAuthIdentifier();

        $canView = $user?->can('message.moderate') === true
            || (
                ($user?->can('class_wall.post') === true || $user?->can('message.send') === true)
                && $audience->canAccessClass($organizationId, $group, $userId)
            );

        abort_unless($canView, HttpResponse::HTTP_FORBIDDEN);

        $posts = ClassWallPost::query()
            ->forOrganization($organizationId)
            ->where('group_id', $group)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate();

        $summaries->attachToPosts($posts->getCollection(), $organizationId);

        return ClassWallPostResource::collection($posts);
    }
}

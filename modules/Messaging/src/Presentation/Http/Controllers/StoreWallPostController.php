<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Messaging\Application\Actions\PublishWallPostAction;
use Modules\Messaging\Application\Services\ClassWallSummaryEnricher;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;
use Modules\Messaging\Presentation\Http\Requests\StoreWallPostRequest;
use Modules\Messaging\Presentation\Http\Resources\ClassWallPostResource;
use Shared\Support\BusinessRuleViolation;
use Symfony\Component\HttpFoundation\Response;

/**
 * نشر منشور على حائط الصف.
 */
final class StoreWallPostController extends Controller
{
    public function __construct(
        private readonly PublishWallPostAction $action,
    ) {}

    public function __invoke(
        StoreWallPostRequest $request,
        ClassWallSummaryEnricher $summaries,
        ClassAudienceQueries $audience,
    ): JsonResponse {
        /** @var string $organizationId */
        $organizationId = $request->user()->organization_id;
        $groupId = $request->string('group_id')->toString();
        $actorId = (string) $request->user()->getAuthIdentifier();

        // نفس فحص الوصول الذي ستكرره PublishWallPostAction لاحقًا — لازم يسبق
        // رفع الصورة إلى القرص، وإلا بقيت ملفات يتيمة كل ما تُرفض محاولة نشر
        // لصف لا ينتمي إليه صاحبها.
        if (!$audience->canAccessClass($organizationId, $groupId, $actorId)) {
            throw BusinessRuleViolation::make(
                'messaging.class_access_denied',
                'messaging::errors.class_access_denied',
            );
        }

        $attachments = array_values($request->array('attachments'));

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $disk = (string) config('messaging.wall.attachments.disk');
            $directory = trim((string) config('messaging.wall.attachments.directory'), '/').'/'.$groupId;
            $path = $file->store($directory, $disk);

            $attachments[] = [
                'type' => 'image',
                'disk' => $disk,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'id' => (string) Str::ulid(),
            ];
        }

        $post = $this->action->execute(
            organizationId: $organizationId,
            groupId: $groupId,
            authorUserId: $actorId,
            body: $request->string('body')->toString(),
            attachments: $attachments,
            isPinned: (bool) $request->boolean('is_pinned', false),
        );

        $summaries->attachToPosts([$post], $organizationId);

        return (new ClassWallPostResource($post))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Content\Application\Actions\UploadCourseMaterialAction;
use Modules\Content\Presentation\Http\Requests\StoreCourseMaterialRequest;
use Modules\Content\Presentation\Http\Resources\CourseMaterialResource;

/**
 * رفع مادة تعليمية جديدة.
 */
final class UploadCourseMaterialController extends Controller
{
    public function __construct(
        private readonly UploadCourseMaterialAction $action,
    ) {}

    public function __invoke(StoreCourseMaterialRequest $request): JsonResponse
    {
        $data = $request->validated();
        $material = $this->action->execute(
            organizationId: (string) $request->user()->organization_id,
            data: $data,
            reason: (string) $data['reason'],
            actorId: (string) $request->user()->getAuthIdentifier(),
        );

        return CourseMaterialResource::make($material)
            ->response()
            ->setStatusCode(201);
    }
}

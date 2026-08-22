<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Content\Application\Actions\UpdateCourseMaterialAction;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Presentation\Http\Requests\UpdateCourseMaterialRequest;
use Modules\Content\Presentation\Http\Resources\CourseMaterialResource;

/**
 * تعديل مادة تعليمية قائمة.
 */
final class UpdateCourseMaterialController extends Controller
{
    public function __construct(
        private readonly UpdateCourseMaterialAction $action,
    ) {}

    public function __invoke(UpdateCourseMaterialRequest $request, CourseMaterial $material): JsonResponse
    {
        $updated = $this->action->execute($material->id, $request->validated());

        return CourseMaterialResource::make($updated)->response();
    }
}

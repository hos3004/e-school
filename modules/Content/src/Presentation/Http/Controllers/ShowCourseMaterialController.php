<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Presentation\Http\Resources\CourseMaterialResource;

/**
 * عرض مادة تعليمية واحدة.
 */
final class ShowCourseMaterialController extends Controller
{
    public function __invoke(Request $request, CourseMaterial $material): JsonResponse
    {
        abort_unless($request->user()?->can('view', $material) ?? false, 403);

        return CourseMaterialResource::make($material)->response();
    }
}

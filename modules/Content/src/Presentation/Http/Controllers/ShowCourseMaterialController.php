<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Presentation\Http\Resources\CourseMaterialResource;

/**
 * عرض مادة تعليمية واحدة.
 */
final class ShowCourseMaterialController extends Controller
{
    public function __invoke(CourseMaterial $material): JsonResponse
    {
        return CourseMaterialResource::make($material)->response();
    }
}

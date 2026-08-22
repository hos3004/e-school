<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Presentation\Http\Requests\ListCourseMaterialsRequest;
use Modules\Content\Presentation\Http\Resources\CourseMaterialResource;

/**
 * سرد المواد التعليمية مع مُصفّيات الكورس والنوع والظهور.
 */
final class ListCourseMaterialsController extends Controller
{
    public function __invoke(ListCourseMaterialsRequest $request): AnonymousResourceCollection
    {
        $query = CourseMaterial::query();

        if ($courseId = $request->validated('course_id')) {
            $query->forCourse((string) $courseId);
        }

        if ($type = $request->validated('type')) {
            $query->ofType(MaterialType::from((string) $type));
        }

        if ($request->boolean('only_active')) {
            $query->active();
        }

        /** @var LengthAwarePaginator $page */
        $page = $query
            ->orderByDesc('created_at')
            ->paginate((int) ($request->validated('per_page') ?? 15));

        return CourseMaterialResource::collection($page);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\CreateCourseAction;
use Modules\Academics\Presentation\Http\Requests\StoreCourseRequest;
use Modules\Academics\Presentation\Http\Resources\CourseResource;

/**
 * إنشاء كورس داخل مستوى.
 */
final class StoreCourseController extends Controller
{
    public function __construct(
        private readonly CreateCourseAction $action,
    ) {}

    public function __invoke(StoreCourseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['organization_id'] = (string) $request->user()->organization_id;
        $course = $this->action->execute(
            $data,
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return CourseResource::make($course)
            ->response()
            ->setStatusCode(201);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\UpdateCourseAction;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Presentation\Http\Requests\UpdateCourseRequest;
use Modules\Academics\Presentation\Http\Resources\CourseResource;

/**
 * تحديث كورس.
 */
final class UpdateCourseController extends Controller
{
    public function __construct(
        private readonly UpdateCourseAction $action,
    ) {}

    public function __invoke(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $course = $this->action->execute($course, $request->validated());

        return CourseResource::make($course)->response();
    }
}

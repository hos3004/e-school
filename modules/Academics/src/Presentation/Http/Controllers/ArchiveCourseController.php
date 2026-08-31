<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\ArchiveCourseAction;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Presentation\Http\Requests\ArchiveCourseRequest;
use Modules\Academics\Presentation\Http\Resources\CourseResource;

/**
 * أرشفة كورس.
 */
final class ArchiveCourseController extends Controller
{
    public function __construct(
        private readonly ArchiveCourseAction $action,
    ) {}

    public function __invoke(ArchiveCourseRequest $request, Course $course): JsonResponse
    {
        $course = $this->action->execute(
            $course,
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return CourseResource::make($course)->response();
    }
}

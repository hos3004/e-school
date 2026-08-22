<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Reporting\Application\Actions\CorrectStudentDashboardAction;
use Modules\Reporting\Presentation\Http\Requests\CorrectStudentDashboardRequest;
use Modules\Reporting\Presentation\Http\Resources\StudentDashboardResource;

/**
 * تصحيح يدوي موثّق لعدّاد في لوحة طالب.
 */
final class CorrectStudentDashboardController extends Controller
{
    public function __construct(
        private readonly CorrectStudentDashboardAction $action,
    ) {}

    public function __invoke(CorrectStudentDashboardRequest $request): JsonResponse
    {
        $dashboard = $this->action->execute($request->validated());

        return StudentDashboardResource::make($dashboard)->response();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Actions\RemoveQuestionAction;
use Modules\Assessments\Domain\Models\Question;
use Modules\Assessments\Presentation\Http\Requests\RemoveQuestionRequest;

/**
 * حذف سؤال من اختبار.
 */
final class RemoveQuestionController extends Controller
{
    public function __construct(
        private readonly RemoveQuestionAction $action,
    ) {}

    public function __invoke(RemoveQuestionRequest $request, string $question): JsonResponse
    {
        $questionModel = Question::query()->findOrFail($question);

        Gate::authorize('delete', $questionModel);

        $this->action->execute(
            $questionModel,
            (string) $request->user()?->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return response()->json(status: 204);
    }
}

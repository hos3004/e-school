<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Actions\RemoveQuestionAction;
use Modules\Assessments\Domain\Models\Question;

/**
 * حذف سؤال من اختبار.
 */
final class RemoveQuestionController extends Controller
{
    public function __construct(
        private readonly RemoveQuestionAction $action,
    ) {}

    public function __invoke(string $question): JsonResponse
    {
        $questionModel = Question::query()->findOrFail($question);

        Gate::authorize('delete', $questionModel);

        $this->action->execute($questionModel);

        return response()->json(status: 204);
    }
}

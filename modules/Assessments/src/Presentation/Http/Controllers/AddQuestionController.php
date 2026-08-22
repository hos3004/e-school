<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Assessments\Application\Actions\AddQuestionAction;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Http\Requests\AddQuestionRequest;
use Modules\Assessments\Presentation\Http\Resources\QuestionResource;

/**
 * إضافة سؤال إلى اختبار.
 */
final class AddQuestionController extends Controller
{
    public function __construct(
        private readonly AddQuestionAction $action,
    ) {}

    public function __invoke(AddQuestionRequest $request, string $assessment): QuestionResource
    {
        $assessmentModel = Assessment::query()->findOrFail($assessment);

        Gate::authorize('manageQuestions', $assessmentModel);

        $question = $this->action->execute($assessmentModel, $request->validated());

        return new QuestionResource($question);
    }
}

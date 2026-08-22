<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Recordings\Application\Actions\DeleteRecordingAction;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Presentation\Http\Requests\DeleteRecordingRequest;

/**
 * حذف تسجيل (تعليق) بسبب موثّق.
 */
final class DeleteRecordingController extends Controller
{
    public function __construct(
        private readonly DeleteRecordingAction $action,
    ) {}

    public function __invoke(DeleteRecordingRequest $request, string $recording): JsonResponse
    {
        $recordingModel = Recording::query()->findOrFail($recording);

        Gate::authorize('delete', $recordingModel);

        $this->action->execute(
            $recordingModel,
            (string) $request->validated('reason'),
        );

        return response()->json(null, 204);
    }
}

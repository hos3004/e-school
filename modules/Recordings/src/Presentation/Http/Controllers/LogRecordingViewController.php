<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Recordings\Application\Actions\LogRecordingViewAction;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Presentation\Http\Requests\LogRecordingViewRequest;

/**
 * تسجيل مشاهدة أو تنزيل — يُرجع 204 بدون محتوى.
 */
final class LogRecordingViewController extends Controller
{
    public function __construct(
        private readonly LogRecordingViewAction $action,
    ) {}

    public function __invoke(LogRecordingViewRequest $request, string $recording): JsonResponse
    {
        $recordingModel = Recording::query()->findOrFail($recording);

        $this->action->execute(
            recording: $recordingModel,
            userId: (string) $request->user()->getKey(),
            action: (string) $request->validated('action'),
            ipAddress: (string) $request->ip(),
            userAgent: (string) substr((string) $request->userAgent(), 0, 500),
        );

        return response()->json(null, 204);
    }
}

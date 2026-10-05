<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Recordings\Application\Actions\LogRecordingViewAction;
use Modules\Recordings\Application\Services\RecordingAccessDecision;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Presentation\Http\Requests\LogRecordingViewRequest;

/**
 * تسجيل مشاهدة أو تنزيل — يُرجع 204 بدون محتوى.
 */
final class LogRecordingViewController extends Controller
{
    public function __construct(
        private readonly LogRecordingViewAction $action,
        private readonly RecordingAccessDecision $access,
    ) {}

    public function __invoke(LogRecordingViewRequest $request, string $recording): JsonResponse
    {
        $recordingModel = Recording::query()
            ->forOrganization((string) $request->user()->getAttribute('organization_id'))
            ->findOrFail($recording);
        $allowed = $request->validated('action') === 'download'
            ? $this->access->canDownload($request->user(), $recordingModel)
            : $this->access->canView($request->user(), $recordingModel);
        abort_unless($allowed, 403);

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

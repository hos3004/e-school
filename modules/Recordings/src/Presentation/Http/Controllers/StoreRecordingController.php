<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Recordings\Application\Actions\RegisterRecordingAction;
use Modules\Recordings\Presentation\Http\Requests\StoreRecordingRequest;
use Modules\Recordings\Presentation\Http\Resources\RecordingResource;

/**
 * تسجيل ملف تسجيل جديد.
 */
final class StoreRecordingController extends Controller
{
    public function __construct(
        private readonly RegisterRecordingAction $action,
    ) {}

    public function __invoke(StoreRecordingRequest $request): JsonResponse
    {
        $recording = $this->action->execute(
            organizationId: (string) $request->user()->getAttribute('organization_id'),
            sessionId: (string) $request->validated('session_id'),
            classroomId: (string) $request->validated('classroom_id'),
            provider: (string) $request->validated('provider'),
            externalRecordingId: (string) $request->validated('external_recording_id'),
            disk: (string) $request->validated('disk'),
            path: (string) $request->validated('path'),
            thumbnailPath: $request->validated('thumbnail_path'),
            durationSeconds: $request->validated('duration_seconds'),
            sizeBytes: $request->validated('size_bytes'),
            actorId: (string) $request->user()->getAuthIdentifier(),
            reason: (string) $request->validated('reason'),
        );

        return (new RecordingResource($recording))
            ->response()
            ->setStatusCode(201);
    }
}

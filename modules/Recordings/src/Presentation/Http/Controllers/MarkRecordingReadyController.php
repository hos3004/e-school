<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Recordings\Application\Actions\MarkRecordingReadyAction;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Presentation\Http\Resources\RecordingResource;

/**
 * إعلان جاهزية التسجيل — يُستدعى من webhook المزوّد أو يدويًا من الإدارة.
 */
final class MarkRecordingReadyController extends Controller
{
    public function __construct(
        private readonly MarkRecordingReadyAction $action,
    ) {}

    public function __invoke(Request $request, string $recording): RecordingResource
    {
        $recordingModel = Recording::query()->findOrFail($recording);

        Gate::authorize('manageLifecycle', $recordingModel);

        $updated = $this->action->execute(
            $recordingModel,
            durationSeconds: $request->integer('duration_seconds') ?: null,
            sizeBytes: $request->integer('size_bytes') ?: null,
            thumbnailPath: $request->input('thumbnail_path'),
        );

        return new RecordingResource($updated);
    }
}

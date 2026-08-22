<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Presentation\Http\Resources\RecordingResource;

/**
 * عرض تسجيل واحد.
 */
final class ShowRecordingController extends Controller
{
    public function __invoke(string $recording): RecordingResource
    {
        $recordingModel = Recording::query()->findOrFail($recording);

        Gate::authorize('view', $recordingModel);

        return new RecordingResource($recordingModel);
    }
}

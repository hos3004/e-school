<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Recordings\Application\Services\RecordingAccessDecision;
use Modules\Recordings\Domain\Models\Recording;
use Modules\Recordings\Presentation\Http\Resources\RecordingResource;

/**
 * عرض تسجيل واحد.
 */
final class ShowRecordingController extends Controller
{
    public function __construct(private readonly RecordingAccessDecision $access) {}

    public function __invoke(Request $request, string $recording): RecordingResource
    {
        $recordingModel = Recording::query()
            ->forOrganization((string) $request->user()->getAttribute('organization_id'))
            ->findOrFail($recording);

        abort_unless($this->access->canView($request->user(), $recordingModel), 403);

        return new RecordingResource($recordingModel);
    }
}

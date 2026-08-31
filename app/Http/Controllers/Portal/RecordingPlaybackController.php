<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Application\Queries\RecordingAccessCoordinator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Recordings\Application\Actions\LogRecordingViewAction;
use Modules\Recordings\Domain\Contracts\RecordingAdministrationQueries;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;

final class RecordingPlaybackController
{
    public function __construct(
        private readonly LogRecordingViewAction $logView,
        private readonly RecordingAdministrationQueries $recordings,
        private readonly RecordingAccessCoordinator $access,
    ) {}

    public function __invoke(Request $request, string $recording): RedirectResponse
    {
        $user = $request->user();
        $userId = (string) $user?->getAuthIdentifier();
        $organizationId = (string) $user?->getAttribute('organization_id');
        $recordingModel = Recording::query()
            ->where('id', $recording)
            ->where('organization_id', $organizationId)
            ->where('status', RecordingStatus::Ready)
            ->firstOrFail();

        $administration = $this->recordings->findForOrganization($organizationId, (string) $recordingModel->getKey());
        abort_unless($user !== null && $administration !== null && $this->access->canWatch($user, $administration), 403);
        abort_unless(filter_var($recordingModel->path, FILTER_VALIDATE_URL) !== false, 404);

        $this->logView->execute(
            recording: $recordingModel,
            userId: $userId,
            ipAddress: (string) $request->ip(),
            userAgent: substr((string) $request->userAgent(), 0, 500),
        );

        return redirect()->away($recordingModel->path);
    }
}

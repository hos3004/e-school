<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\SubmitStudentSessionApologyRequest;
use Illuminate\Http\RedirectResponse;
use Modules\Sessions\Application\Actions\SubmitStudentSessionApologyAction;

final class StudentSessionApologyController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly SubmitStudentSessionApologyAction $submit,
    ) {}

    public function __invoke(SubmitStudentSessionApologyRequest $request, string $session): RedirectResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $studentProfileId = $this->data->studentProfileId($actorId, $organizationId);
        abort_if($organizationId === '' || $studentProfileId === null, 403);

        $this->submit->execute(
            organizationId: $organizationId,
            sessionId: $session,
            studentProfileId: $studentProfileId,
            actorId: $actorId,
            reason: (string) $request->validated('reason'),
        );

        return back()->with('success', __('sessions::messages.student_apology_submitted'));
    }
}

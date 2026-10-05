<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\RequestSessionPostponementRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Scheduling\Application\Actions\ApprovePostponement;
use Modules\Scheduling\Application\Actions\RequestPostponement;
use Modules\Scheduling\Domain\Models\PostponementRequest;

final class SessionPostponementRequestController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly RequestPostponement $requestPostponement,
        private readonly ApprovePostponement $approvePostponement,
    ) {}

    public function student(RequestSessionPostponementRequest $request, string $session): RedirectResponse
    {
        return $this->submit($request, $session, false);
    }

    public function teacher(RequestSessionPostponementRequest $request, string $session): RedirectResponse
    {
        return $this->submit($request, $session, true);
    }

    public function acceptAlternative(Request $request, string $postponement): RedirectResponse
    {
        $user = $request->user();
        abort_unless((bool) $user->can('session.postpone.request'), 403);
        $organizationId = (string) $user->getAttribute('organization_id');
        $actorId = (string) $user->getAuthIdentifier();
        $studentProfileId = $this->data->studentProfileId($actorId, $organizationId);
        abort_if($organizationId === '' || $studentProfileId === null, 403);

        $record = PostponementRequest::query()
            ->forOrganization($organizationId)
            ->whereKey($postponement)
            ->first();
        abort_if($record === null, 404);
        abort_unless((string) $record->requested_for_student_id === $studentProfileId, 403);
        abort_if($record->proposed_by_teacher_start === null, 422);

        $this->approvePostponement->execute(
            organizationId: $organizationId,
            requestId: (string) $record->getKey(),
            approvedBy: $actorId,
            agreedStart: $record->proposed_by_teacher_start,
            reason: (string) __('scheduling::messages.student_approved_postponement'),
        );

        return back()->with('success', __('scheduling::messages.postponement_approved'));
    }

    private function submit(
        RequestSessionPostponementRequest $request,
        string $session,
        bool $asTeacher,
    ): RedirectResponse {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $actorId = (string) $request->user()?->getAuthIdentifier();
        $studentProfileId = $asTeacher ? null : $this->data->studentProfileId($actorId, $organizationId);
        $staffProfileId = $asTeacher ? $this->data->staffProfileId($actorId, $organizationId) : null;

        abort_if($organizationId === '' || ($asTeacher ? $staffProfileId === null : $studentProfileId === null), 403);
        $validated = $request->validated();
        $proposedStart = CarbonImmutable::parse((string) $validated['proposed_start'], 'UTC');

        $postponement = $this->requestPostponement->execute(
            organizationId: $organizationId,
            sessionId: $session,
            requestedBy: $actorId,
            studentProfileId: $studentProfileId,
            proposedStart: $proposedStart,
            reason: (string) $validated['reason'],
            requestingStaffProfileId: $staffProfileId,
        );

        if ($asTeacher) {
            $this->approvePostponement->execute(
                organizationId: $organizationId,
                requestId: (string) $postponement->getKey(),
                approvedBy: $actorId,
                agreedStart: $proposedStart,
                reason: (string) $validated['reason'],
            );

            return back()->with('success', __('scheduling::messages.postponement_approved'));
        }

        return back()->with('success', __('scheduling::messages.postponement_requested'));
    }
}

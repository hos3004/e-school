<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\RespondToPostponementRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Scheduling\Application\Actions\ApprovePostponement;
use Modules\Scheduling\Application\Actions\ProposePostponementAlternative;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;

final class TeacherPostponementResponseController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly SessionSchedulingQueries $sessions,
        private readonly ApprovePostponement $approvePostponement,
        private readonly ProposePostponementAlternative $proposeAlternative,
    ) {}

    public function approve(Request $request, string $postponement): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('session.postpone.approve'), 403);
        [$record, $organizationId] = $this->assignedRequest($request, $postponement);
        abort_if($record->requires_admin_review, 403);

        $this->approvePostponement->execute(
            $organizationId,
            (string) $record->getKey(),
            (string) $request->user()?->getAuthIdentifier(),
            $record->proposed_by_teacher_start ?? $record->proposed_start,
            (string) __('scheduling::messages.teacher_approved_postponement'),
        );

        return back()->with('success', __('scheduling::messages.postponement_approved'));
    }

    public function propose(RespondToPostponementRequest $request, string $postponement): RedirectResponse
    {
        [$record, $organizationId] = $this->assignedRequest($request, $postponement);
        abort_if($record->requires_admin_review, 403);
        $validated = $request->validated();

        $this->proposeAlternative->execute(
            $organizationId,
            (string) $record->getKey(),
            (string) $request->user()?->getAuthIdentifier(),
            CarbonImmutable::parse((string) $validated['proposed_start_at'], 'UTC'),
            (string) $validated['reason'],
        );

        return back()->with('success', __('scheduling::messages.postponement_alternative_proposed'));
    }

    /** @return array{PostponementRequest, string} */
    private function assignedRequest(Request $request, string $postponement): array
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffProfileId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );
        abort_if($organizationId === '' || $staffProfileId === null, 403);

        $record = PostponementRequest::query()->forOrganization($organizationId)->whereKey($postponement)->first();
        abort_if($record === null, 404);
        $session = $this->sessions->find($organizationId, (string) $record->session_id);
        abort_if($session === null || $session->staffProfileId !== $staffProfileId, 403);

        return [$record, $organizationId];
    }
}

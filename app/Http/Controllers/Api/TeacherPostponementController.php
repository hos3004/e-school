<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use App\Http\Requests\Portal\RejectPostponementRequest;
use App\Http\Requests\Portal\RespondToPostponementRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Scheduling\Application\Actions\ApprovePostponement;
use Modules\Scheduling\Application\Actions\ProposePostponementAlternative;
use Modules\Scheduling\Application\Actions\RejectPostponement;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;

/**
 * طلبات تأجيل المعلم للموبايل — نفس بوابة الويب Portal\TeacherPostponementsController
 * وPortal\TeacherPostponementResponseController بالضبط، نفس الأفعال
 * (ApprovePostponement/ProposePostponementAlternative/RejectPostponement)،
 * فقط JSON بدل Inertia/redirect.
 */
final class TeacherPostponementController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly SessionSchedulingQueries $sessions,
        private readonly ApprovePostponement $approvePostponement,
        private readonly ProposePostponementAlternative $proposeAlternative,
        private readonly RejectPostponement $rejectPostponement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = (string) $request->user()?->getAttribute('organization_id');
        $staffId = $this->data->staffProfileId(
            (string) $request->user()?->getAuthIdentifier(),
            $organizationId,
        );

        $requests = $staffId === null
            ? []
            : $this->data->teacherPostponements($staffId, app()->getLocale(), $organizationId);

        return response()->json(['data' => $requests]);
    }

    public function approve(Request $request, string $postponement): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('session.postpone.approve'), 403);
        [$record, $organizationId] = $this->assignedRequest($request, $postponement);
        abort_if($record->requires_admin_review, 403);

        $this->approvePostponement->execute(
            $organizationId,
            (string) $record->getKey(),
            (string) $request->user()->getAuthIdentifier(),
            $record->proposed_by_teacher_start ?? $record->proposed_start,
            (string) __('scheduling::messages.teacher_approved_postponement'),
        );

        return response()->json(['status' => 'approved']);
    }

    public function propose(RespondToPostponementRequest $request, string $postponement): JsonResponse
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

        return response()->json(['status' => 'alternative_proposed']);
    }

    public function reject(RejectPostponementRequest $request, string $postponement): JsonResponse
    {
        [$record, $organizationId] = $this->assignedRequest($request, $postponement);
        abort_if($record->requires_admin_review, 403);

        $this->rejectPostponement->execute(
            $organizationId,
            (string) $record->getKey(),
            (string) $request->user()?->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return response()->json(['status' => 'rejected']);
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

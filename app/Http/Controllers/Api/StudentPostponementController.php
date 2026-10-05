<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Support\PortalData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Scheduling\Application\Actions\ApprovePostponement;
use Modules\Scheduling\Domain\Models\PostponementRequest;

/**
 * قبول الطالب للموعد البديل اللي اقترحه المعلم للتأجيل — مرآة
 * Portal\SessionPostponementRequestController::acceptAlternative() بالضبط.
 */
final class StudentPostponementController extends Controller
{
    public function __construct(
        private readonly PortalData $data,
        private readonly ApprovePostponement $approvePostponement,
    ) {}

    public function acceptAlternative(Request $request, string $postponement): JsonResponse
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

        return response()->json(['status' => 'approved']);
    }
}

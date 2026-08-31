<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Assignments\Application\Actions\SubmitAssignmentAction;
use Modules\Assignments\Domain\Contracts\AssignmentAudienceQueries;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Domain\Models\AssignmentSubmission;
use Modules\Assignments\Presentation\Http\Requests\SubmitOwnAssignmentRequest;
use Shared\Support\Transaction;

/**
 * تسليم الطالب لتكليفه من بوابة الطالب.
 *
 * يعكس `SubmitOwnAssignmentController` حرفيًا في التحقق من الجمهور وقاعدة
 * العمل، ويختلف عنه في الاستجابة فقط. التأخير يقرره `SubmitAssignmentAction`
 * من `due_at` ولا يُرسَل من الواجهة.
 */
final class StudentAssignmentSubmissionController extends Controller
{
    public function __construct(
        private readonly AssignmentAudienceQueries $audiences,
        private readonly SubmitAssignmentAction $submit,
        private readonly Transaction $transaction,
    ) {}

    public function __invoke(
        SubmitOwnAssignmentRequest $request,
        Assignment $assignment,
    ): RedirectResponse {
        $audience = $this->audiences->forUser(
            (string) $assignment->organization_id,
            (string) $request->user()?->getAuthIdentifier(),
        );

        abort_unless(
            $audience->studentProfileId !== null
            && $audience->targetsStudent(
                (string) $assignment->course_id,
                $assignment->group_id === null ? null : (string) $assignment->group_id,
            ),
            403,
        );

        $studentProfileId = $audience->studentProfileId;

        $submission = $this->transaction->run(
            static fn (): AssignmentSubmission => AssignmentSubmission::query()->firstOrCreate(
                [
                    'assignment_id' => (string) $assignment->getKey(),
                    'student_profile_id' => $studentProfileId,
                ],
                [
                    'status' => AssignmentSubmissionStatus::Pending->value,
                    'is_late' => false,
                ],
            ),
        );

        $result = $this->submit->execute(
            $submission,
            $request->validated(),
            (string) $request->user()?->getAuthIdentifier(),
        );

        return back()->with(
            'success',
            $result->is_late
                ? __('portal.assignments.submitted_late')
                : __('portal.assignments.submitted'),
        );
    }
}

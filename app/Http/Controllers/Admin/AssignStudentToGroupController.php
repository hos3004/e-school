<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Actions\AssignStudentToGroupAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignStudentToGroupRequest;
use Illuminate\Http\JsonResponse;

final class AssignStudentToGroupController extends Controller
{
    public function __construct(private readonly AssignStudentToGroupAction $action) {}

    public function __invoke(AssignStudentToGroupRequest $request, string $groupId): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $enrollment = $this->action->execute(
            actorOrganizationId: (string) $user->getAttribute('organization_id'),
            studentProfileId: (string) $data['student_profile_id'],
            programId: (string) $data['program_id'],
            groupId: $groupId,
            courseId: (string) $data['course_id'],
            actorId: (string) $user->getAuthIdentifier(),
            correlationId: $request->header('X-Correlation-Id'),
        );

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->enrollmentId,
                'organization_id' => $enrollment->organizationId,
                'student_profile_id' => $enrollment->studentProfileId,
                'program_id' => $enrollment->programId,
                'status' => $enrollment->status,
            ],
        ], $enrollment->created ? 201 : 200);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\ArchiveStudentAction;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Http\Requests\ArchiveStudentRequest;

/**
 * أرشفة طالب — لا حذف نهائي أبدًا.
 */
final class ArchiveStudentController extends Controller
{
    public function __construct(
        private readonly ArchiveStudentAction $action,
    ) {}

    public function __invoke(ArchiveStudentRequest $request, StudentProfile $student): JsonResponse
    {
        $this->action->execute($student, (string) $request->string('reason'));

        return response()->json(null, 204);
    }
}

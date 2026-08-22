<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\RestoreStudentAction;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Http\Resources\StudentProfileResource;

/**
 * استرجاع طالب مؤرشف.
 */
final class RestoreStudentController extends Controller
{
    public function __construct(
        private readonly RestoreStudentAction $action,
    ) {}

    public function __invoke(Request $request, string $student): StudentProfileResource
    {
        abort_unless($request->user()?->can('restore', $this->findForAuthorization($student)), 403);

        return StudentProfileResource::make($this->action->execute($student));
    }

    private function findForAuthorization(string $studentId): ?StudentProfile
    {
        /** @var StudentProfile|null */
        return StudentProfile::query()->withTrashed()->find($studentId);
    }
}

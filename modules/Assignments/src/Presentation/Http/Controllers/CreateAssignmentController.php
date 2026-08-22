<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Assignments\Application\Actions\CreateAssignmentAction;
use Modules\Assignments\Presentation\Http\Requests\CreateAssignmentRequest;
use Modules\Assignments\Presentation\Http\Resources\AssignmentResource;

/**
 * إنشاء نشاط جديد.
 */
final class CreateAssignmentController extends Controller
{
    public function __construct(
        private readonly CreateAssignmentAction $action,
    ) {}

    public function __invoke(CreateAssignmentRequest $request): AssignmentResource
    {
        $data = array_merge($request->validated(), [
            'organization_id' => (string) Auth::user()?->organization_id,
        ]);

        return new AssignmentResource($this->action->execute($data));
    }
}

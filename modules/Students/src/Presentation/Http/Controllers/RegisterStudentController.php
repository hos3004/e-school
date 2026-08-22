<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Students\Application\Actions\RegisterStudentAction;
use Modules\Students\Presentation\Http\Requests\RegisterStudentRequest;
use Modules\Students\Presentation\Http\Resources\StudentProfileResource;

/**
 * تسجيل طالب جديد.
 */
final class RegisterStudentController extends Controller
{
    public function __construct(
        private readonly RegisterStudentAction $action,
    ) {}

    public function __invoke(RegisterStudentRequest $request): JsonResponse
    {
        $student = $this->action->execute($request->validated());

        return StudentProfileResource::make($student)
            ->response()
            ->setStatusCode(201);
    }
}

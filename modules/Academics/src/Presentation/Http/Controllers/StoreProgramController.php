<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\CreateProgramAction;
use Modules\Academics\Presentation\Http\Requests\StoreProgramRequest;
use Modules\Academics\Presentation\Http\Resources\ProgramResource;

/**
 * إنشاء برنامج أكاديمي.
 */
final class StoreProgramController extends Controller
{
    public function __construct(
        private readonly CreateProgramAction $action,
    ) {}

    public function __invoke(StoreProgramRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['organization_id'] = (string) $request->user()->organization_id;
        $program = $this->action->execute(
            $data,
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return ProgramResource::make($program)
            ->response()
            ->setStatusCode(201);
    }
}

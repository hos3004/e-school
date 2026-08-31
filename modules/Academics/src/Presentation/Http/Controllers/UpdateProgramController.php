<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\UpdateProgramAction;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Presentation\Http\Requests\UpdateProgramRequest;
use Modules\Academics\Presentation\Http\Resources\ProgramResource;

/**
 * تحديث برنامج أكاديمي.
 */
final class UpdateProgramController extends Controller
{
    public function __construct(
        private readonly UpdateProgramAction $action,
    ) {}

    public function __invoke(UpdateProgramRequest $request, Program $program): JsonResponse
    {
        $program = $this->action->execute(
            $program,
            $request->validated(),
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return ProgramResource::make($program)->response();
    }
}

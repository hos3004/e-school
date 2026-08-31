<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\ArchiveProgramAction;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Presentation\Http\Requests\ArchiveProgramRequest;
use Modules\Academics\Presentation\Http\Resources\ProgramResource;

/**
 * أرشفة برنامج أكاديمي.
 */
final class ArchiveProgramController extends Controller
{
    public function __construct(
        private readonly ArchiveProgramAction $action,
    ) {}

    public function __invoke(ArchiveProgramRequest $request, Program $program): JsonResponse
    {
        $program = $this->action->execute(
            $program,
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return ProgramResource::make($program)->response();
    }
}

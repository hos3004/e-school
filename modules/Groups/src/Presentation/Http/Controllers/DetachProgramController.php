<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\DetachProgramAction;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Presentation\Http\Requests\DetachProgramRequest;

/**
 * فك ربط برنامج عن مجموعة.
 */
final class DetachProgramController extends Controller
{
    public function __construct(
        private readonly DetachProgramAction $action,
    ) {}

    public function __invoke(DetachProgramRequest $request, GroupProgram $link): JsonResponse
    {
        $this->action->execute(
            $link,
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return response()->json(null, 204);
    }
}

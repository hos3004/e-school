<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\ReorderLevelsAction;
use Modules\Academics\Presentation\Http\Requests\ReorderLevelsRequest;

/**
 * إعادة ترتيب مستويات برنامج.
 */
final class ReorderLevelsController extends Controller
{
    public function __construct(
        private readonly ReorderLevelsAction $action,
    ) {}

    public function __invoke(ReorderLevelsRequest $request): JsonResponse
    {
        $this->action->execute(
            (string) $request->validated('program_id'),
            array_values((array) $request->validated('level_ids')),
        );

        return response()->json(status: 204);
    }
}

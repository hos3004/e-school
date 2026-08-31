<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\UpdateLevelAction;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Presentation\Http\Requests\UpdateLevelRequest;
use Modules\Academics\Presentation\Http\Resources\LevelResource;

/**
 * تحديث مستوى.
 */
final class UpdateLevelController extends Controller
{
    public function __construct(
        private readonly UpdateLevelAction $action,
    ) {}

    public function __invoke(UpdateLevelRequest $request, Level $level): JsonResponse
    {
        $level = $this->action->execute(
            $level,
            $request->validated(),
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return LevelResource::make($level)->response();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Academics\Application\Actions\CreateLevelAction;
use Modules\Academics\Presentation\Http\Requests\StoreLevelRequest;
use Modules\Academics\Presentation\Http\Resources\LevelResource;

/**
 * إنشاء مستوى داخل برنامج.
 */
final class StoreLevelController extends Controller
{
    public function __construct(
        private readonly CreateLevelAction $action,
    ) {}

    public function __invoke(StoreLevelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['organization_id'] = (string) $request->user()->organization_id;
        $level = $this->action->execute(
            $data,
            (string) $request->user()->getAuthIdentifier(),
            (string) $request->validated('reason'),
        );

        return LevelResource::make($level)
            ->response()
            ->setStatusCode(201);
    }
}

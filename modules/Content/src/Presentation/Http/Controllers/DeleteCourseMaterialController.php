<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Content\Application\Actions\DeleteCourseMaterialAction;
use Modules\Content\Domain\Models\CourseMaterial;
use Modules\Content\Presentation\Http\Requests\DeleteCourseMaterialRequest;

/**
 * إزالة مادة تعليمية (تعليق) مع سبب موثّق.
 */
final class DeleteCourseMaterialController extends Controller
{
    public function __construct(
        private readonly DeleteCourseMaterialAction $action,
    ) {}

    public function __invoke(DeleteCourseMaterialRequest $request, CourseMaterial $material): Response
    {
        $this->action->execute(
            $material,
            (string) $request->validated('reason'),
            (string) $request->user()->getAuthIdentifier(),
        );

        return response()->noContent();
    }
}

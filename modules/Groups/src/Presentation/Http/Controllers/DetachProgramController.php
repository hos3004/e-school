<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\DetachProgramAction;
use Modules\Groups\Domain\Models\GroupProgram;

/**
 * فك ربط برنامج عن مجموعة.
 */
final class DetachProgramController extends Controller
{
    public function __construct(
        private readonly DetachProgramAction $action,
    ) {}

    public function __invoke(GroupProgram $link): JsonResponse
    {
        abort_unless(
            request()->user()?->can('delete', $link) ?? false,
            403,
        );

        $this->action->execute($link);

        return response()->json(null, 204);
    }
}

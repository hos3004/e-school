<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\UnassignTeacherAction;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Groups\Presentation\Http\Resources\GroupTeacherResource;

/**
 * إلغاء إسناد معلم عن مجموعة.
 */
final class UnassignTeacherController extends Controller
{
    public function __construct(
        private readonly UnassignTeacherAction $action,
    ) {}

    public function __invoke(GroupTeacher $assignment): JsonResponse
    {
        abort_unless(
            request()->user()?->can('delete', $assignment) ?? false,
            403,
        );

        $assignment = $this->action->execute($assignment);

        return GroupTeacherResource::make($assignment)->response();
    }
}

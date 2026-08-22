<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Groups\Application\Actions\AttachProgramAction;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Http\Requests\AttachProgramRequest;
use Modules\Groups\Presentation\Http\Resources\GroupResource;

/**
 * ربط برنامج بمجموعة — يعيد تمثيل المجموعة بعد الإرفاق.
 */
final class AttachProgramController extends Controller
{
    public function __construct(
        private readonly AttachProgramAction $action,
    ) {}

    public function __invoke(AttachProgramRequest $request, Group $group): JsonResponse
    {
        $this->action->execute($group, (string) $request->validated('program_id'));

        return GroupResource::make($group->refresh())->response();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Staff\Domain\Enums\TeacherLeaveStatus;
use Modules\Staff\Domain\Models\TeacherLeave;
use Modules\Staff\Presentation\Http\Resources\TeacherLeaveResource;

final class IndexTeacherLeavesController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $user = request()?->user();

        abort_unless($user !== null && ($user->can('staff.leave.view_any') || $user->can('staff.leave.view')), 403);

        $leaves = TeacherLeave::query()
            ->when(
                request()->filled('status'),
                fn ($q) => $q->where('status', TeacherLeaveStatus::from((string) request()->string('status'))),
            )
            ->orderByDesc('starts_at')
            ->paginate((int) config('staff.pagination.per_page', 25));

        return TeacherLeaveResource::collection($leaves);
    }
}

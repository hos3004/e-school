<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Http\Resources\StudentProfileResource;

/**
 * فهرس ملفات الطلاب — متحكم رفيع بلا منطق عمل.
 */
final class ListStudentProfilesController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        abort_unless(request()->user()?->can('viewAny', StudentProfile::class), 403);

        $students = StudentProfile::query()
            ->when(
                request()->filled('organization_id'),
                fn ($query) => $query->forOrganization((string) request()->string('organization_id')),
            )
            ->orderByDesc('created_at')
            ->paginate(min(
                max(request()->integer('per_page', (int) config('students.pagination.per_page')), 1),
                (int) config('students.pagination.max_per_page'),
            ));

        return StudentProfileResource::collection($students);
    }
}

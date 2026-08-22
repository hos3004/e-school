<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Http\Resources\StudentProfileResource;

/**
 * فهرس ملفات الطلاب — متحكم رفيع بلا منطق عمل.
 */
final class ListStudentProfilesController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $user = request()->user();
        abort_unless($user?->can('viewAny', StudentProfile::class), 403);

        $organizationId = (string) data_get($user, 'organization_id');
        abort_if($organizationId === '', 403);

        $status = RegistrationStatus::tryFrom((string) request()->query('status'));
        $search = trim((string) request()->query('search'));

        $students = StudentProfile::query()
            ->forOrganization($organizationId)
            ->when(request()->filled('country_id'), fn ($query) => $query->where('country_id', request()->string('country_id')->toString()))
            ->when(request()->filled('region_id'), fn ($query) => $query->where('region_id', request()->string('region_id')->toString()))
            ->when($status !== null, fn ($query) => $query->whereHas(
                'registrationApplication',
                fn ($registrationQuery) => $registrationQuery->where('status', $status->value),
            ))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('student_code', 'ilike', "%{$search}%")
                    ->orWhereHas(
                        'registrationApplication',
                        fn ($registrationQuery) => $registrationQuery->where('full_name', 'ilike', "%{$search}%"),
                    );
            }))
            ->orderByDesc('created_at')
            ->paginate(min(
                max(request()->integer('per_page', (int) config('students.pagination.per_page')), 1),
                (int) config('students.pagination.max_per_page'),
            ));

        return StudentProfileResource::collection($students);
    }
}

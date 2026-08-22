<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Reporting\Domain\Contracts\DashboardQuery;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Reporting\Domain\ValueObjects\StudentDashboardRow;
use Symfony\Component\HttpFoundation\Response;

/**
 * عرض لوحة طالب واحد عبر عقد القراءة.
 */
final class ShowStudentDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardQuery $query,
    ) {}

    public function __invoke(string $enrollmentId): JsonResponse
    {
        Gate::authorize('viewAny', StudentDashboard::class);

        /** @var StudentDashboardRow|null $row */
        $row = $this->query->studentByEnrollment(
            (string) auth()->user()?->organization_id,
            $enrollmentId,
        );

        if ($row === null) {
            return response()->json(
                ['message' => __('reporting::errors.dashboard_not_found', ['enrollment_id' => $enrollmentId])],
                Response::HTTP_NOT_FOUND,
            );
        }

        return response()->json($row->toArray());
    }
}

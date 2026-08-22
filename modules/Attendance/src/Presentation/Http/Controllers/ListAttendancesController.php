<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Presentation\Http\Resources\AttendanceResource;

/**
 * قائمة قيود الحضور — GET /api/attendances.
 *
 * تصفية اختيارية بالحالة أو بما لم يُعتمد بعد. لا منطق عمل هنا.
 */
final class ListAttendancesController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        abort_unless(auth()->user()?->can('viewAny', Attendance::class) ?? false, 403);

        $filters = $this->validateFilters($request);

        /** @var LengthAwarePaginator<int, Attendance> $page */
        $page = Attendance::query()
            ->when(
                isset($filters['status']),
                fn ($query) => $query->withStatus(AttendanceStatus::from((string) $filters['status'])),
            )
            ->when(
                (bool) ($filters['unconfirmed'] ?? false),
                fn ($query) => $query->unconfirmed(),
            )
            ->orderByDesc('created_at')
            ->paginate(perPage: (int) ($filters['per_page'] ?? 15));

        return AttendanceResource::collection($page);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['sometimes', 'string', Rule::enum(AttendanceStatus::class)],
            'unconfirmed' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
